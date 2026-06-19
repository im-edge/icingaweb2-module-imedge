<?php

namespace Icinga\Module\Imedge\Controllers;

use Exception;
use gipfl\IcingaWeb2\CompatController;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Imedge\Graphing\RrdDataExporter;
use Icinga\Module\Imedge\Graphing\RrdImageLoader;
use Icinga\Module\Imedge\NodeControl\MetricStoreLookup;
use Icinga\Web\UrlParams;
use IMEdge\RrdGraphInfo\GraphInfo;
use IMEdge\RrdGraphInfo\ImageHelper;
use IMEdge\Web\Grapher\GraphRendering\CommandRenderer;
use IMEdge\Web\Grapher\GraphRendering\RrdImage;
use IMEdge\Web\Grapher\Request\ResponseSender;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function IMEdge\WebCompat\await;
use function base64_decode;
use function strpos;
use function substr;

class GraphController extends CompatController
{
    use WebClientInfo;
    use DbTrait;
    use RestApiMethods;

    protected $requiresAuthentication = true;
    protected ?RrdImageLoader $rrdImageLoader = null;

    public function init()
    {
        /*
        // TODO: re-enable! -> interfered with caching logic
        // TODO: Optional fallback? if Auth::isAuthenticated() && hasPermission for all requested devices?
        $signer = new UrlSigner(Keys::getUrlSigningKey(), [
            'width',
            'height',
            'start',
            'end',
            'rnd'
        ]);
        if (!$signer->validate($this->url())) {
            throw new AuthenticationException('Access denied');
        }
        */
    }

    public function indexAction()
    {
        $this->sendImg($this->loadImg($this->params));
    }

    public function dataAction()
    {
        $this->preventZfLayout();
        $this->runForApi(function () {
            $templateName = $this->params->getRequired('template');
            $image = $this->getRrdImageLoader()->getFileImg($this->getFirstUuidParameter(), $templateName);
            $image->graph->applyUrlParams($this->params);

            $graph = $image->graph->definition;
            $command = RrdDataExporter::prepareExportCommand(
                $graph,
                $this->getExportsForTemplate($templateName),
                $image->graph->timeRange->getEpochStart(),
                $image->graph->timeRange->getEpochEnd(),
                $this->params->getRequired('graphWidth')
            );
            $this->sendJsonResponse(await($image->getClient()->request('rrd.data', [$command])));
        });
    }

    protected function getExportsForTemplate(string $templateName): array
    {
        $exports = [
            'if_traffic' => [
                'ifInBitsMin' => 'bit/s in (min)',
                'ifInBitsAvg' => 'bit/s in (avg)',
                'ifInBitsMax' => 'bit/s in (max)',
                'ifOutBitsMin' => 'bit/s out (min)',
                'ifOutBitsAvg' => 'bit/s out (avg)',
                'ifOutBitsMax' => 'bit/s out (max)',
            ],
            'if_traffic_simple' => [
                'ifInBitsMin' => 'bit/s in (min)',
                'ifInBitsAvg' => 'bit/s in (avg)',
                'ifInBitsMax' => 'bit/s in (max)',
                'ifOutBitsMin' => 'bit/s out (min)',
                'ifOutBitsAvg' => 'bit/s out (avg)',
                'ifOutBitsMax' => 'bit/s out (max)',
            ],
            'if_packets' => [
                'def_average_ifInUcastPkts' => '/s Unicast packets inbound',
                // 'def_average_ifInNUcastPkts' => 'packets/s in Non-Unicast',
                'def_average_ifOutUcastPkts' => '/s Unicast packets outbound',
                // 'def_average_ifOutNUcastPkts' => 'packets/s out Non-Unicast',
            ],
            'if_error' => [
                'ifInDiscards' => '/s discards inbound',
                'ifInErrors' => '/s errors inbound',
                'ifInUnknownProtos' => '/s unknown protocols inbound',
                'ifOutDiscards' => '/s discards outbound',
                'ifOutErrors' => '/s errors outbound',

            ]
        ];

        if (isset($exports[$templateName])) {
            return $exports[$templateName];
        }

        throw new NotFoundError("Export for '$templateName' has not been defined");
    }

    protected function loadImg(UrlParams $params): RrdImage
    {
        $loader = $this->getRrdImageLoader();

        $image = $loader->getFileImg($this->getFirstUuidParameter(), $params->getRequired('template'));
        $image->graph->applyUrlParams($params);
        $image->graph->layout->setDarkMode($this->wantsDarkMode());

        return $image;
    }

    protected function getRrdImageLoader(): RrdImageLoader
    {
        return $this->rrdImageLoader ??= new RrdImageLoader($this->db());
    }

    protected function getFirstUuidParameter(): UuidInterface
    {
        $uuids = [];
        foreach (explode(',', $this->params->getRequired('uuid')) as $uuidString) {
            $uuids[$uuidString] = Uuid::fromString($uuidString);
        }
        if (count($uuids) > 1) {
            throw new \RuntimeException('More than one file UUID is not (yet) supported');
        }
        if (empty($uuids) > 1) {
            throw new \RuntimeException('UUID is required');
        }

        return current($uuids);
    }

    public function sendImg(RrdImage $image)
    {
        $expiration = null;
        if ($image->graph->timeRange->endsNow()) {
            $expiration = 60;
            if ($image->graph->timeRange->getDuration() <= 7200) {
                $expiration = null;
            }
        } else {
            if ($image->graph->timeRange->getEpochEnd() < time()) {
                $expiration = 3600;
            }
        }

        $sender = new ResponseSender($this->getResponse(), $this->getServerRequest(), $expiration);
        if ($this->isXhr()) {
            $sender->useXhr();
        }

        try {
            try {
                if ($this->params->get('showCommand')) {
                    $this->showCommand((string) $image->graph);
                    return;
                }
            } catch (Exception $exception) {
                echo $exception->getMessage();
                exit;
            }
            $this->preventZfLayout();
            $info = $image->getGraphInfo();
            if ($this->isXhr() || $this->params->get('simulateXhr')) {
                $info = $info->jsonSerialize();
                if ($expiration === null) {
                    $info->refresh = true;
                    $this->triggerLiveRefresh($image);
                } else {
                    $info->refresh = false;
                }
                $info->description = $image->getDescription()->render();
                $sender->sendAsJson($info);
                return;
            }
            $rawImage = self::getRawImage($info);
            if ($this->params->get('download')) {
                $sender->sendImage($rawImage, $info->type, 'icinga-img.' . $info->format);
            } else {
                $sender->sendImage($rawImage, $info->type);
            }
        } catch (Exception $e) {
            $sender->sendError($e, $this->getWidth(), $this->getHeight());
        }
    }

    protected static function getRawImage(GraphInfo $info): string
    {
        $raw = $info->raw;
        $imgString = substr($raw, strpos($raw, ',') + 1);
        if ($info->format === 'svg') {
            return ImageHelper::decodeSvgDataString($imgString);
        }

        return base64_decode($imgString);
    }

    protected function showCommand($command)
    {
        $this->assertPermission('metrics/admin');
        $this->addSingleTab('Img');
        $this->addTitle('Graph Rendering - Command');
        $this->content()->add(new CommandRenderer($command));
    }

    protected function getWidth(): int
    {
        return (int) $this->params->get('width', 1200);
    }

    protected function getHeight(): int
    {
        return (int) $this->params->get('height', 120);
    }

    protected function wantsLiveRefresh(): bool
    {
        return preg_match('/^if_traffic/', $this->params->get('template'))
            && $this->params->get('end') === 'now'
            && in_array($this->params->get('start'), [
                'end-15minute',
                'end-30minute',
                'end-1hour',
                'end-2hour',
            ]);
    }

    protected function triggerLiveRefresh(RrdImage $image): void
    {
        if ($this->wantsLiveRefresh()) {
            // TODO: image client is RRD node, not main?!
            $store = new MetricStoreLookup($this->db());
            $nodeUuid = $store->getMetricStoreNodeUuid(Uuid::fromString($image->getClient()->getTarget()));
            $refreshClient = (new IMEdgeClient())->withTarget($nodeUuid->toString());

            $fileUuid = $this->getFirstUuidParameter();
            $loader = $this->getRrdImageLoader();
            await($refreshClient->request('snmp.triggerScenario', [
                'deviceUuid' => $loader->getDeviceUuidForFile($fileUuid),
                'name'       => 'interfaceTraffic',
                'delay'      => 1,
            ]), Loop::get());
        }
    }

    protected function preventZfLayout()
    {
        $this->_helper->getHelper('layout')->disableLayout();
        $this->getViewRenderer()->disable();
    }
}
