<?php

namespace Icinga\Module\Imedge\Controllers;

use Exception;
use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Imedge\Graphing\RrdImageLoader;
use IMEdge\RrdGraphInfo\ImageHelper;
use IMEdge\Web\Grapher\GraphRendering\CommandRenderer;
use IMEdge\Web\Grapher\Request\ResponseSender;
use Ramsey\Uuid\Uuid;

use function base64_decode;
use function strpos;
use function substr;

class GraphController extends CompatController
{
    use WebClientInfo;
    use DbTrait;

    protected $requiresAuthentication = false;

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
        $this->sendImg();
    }

    public function sendImg()
    {
        $loader = new RrdImageLoader($this->db());
        $uuids = [];
        foreach (explode(',', $this->params->getRequired('uuid')) as $uuidString) {
            $uuids[$uuidString] = Uuid::fromString($uuidString);
        }
        if (count($uuids) > 1) {
            throw new \RuntimeException('More than one file UUID is not (yet) supported');
        }
        $image = $loader->getFileImg(current($uuids), $this->params->getRequired('template'));
        $image->graph->applyUrlParams($this->params);
        $image->graph->layout->setDarkMode($this->wantsDarkMode());

        $expiration = null;
        if ($image->graph->timeRange->endsNow()) {
            $expiration = 60;
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
                $info->description = $image->getDescription()->render();
                $sender->sendAsJson($info);
                return;
            }
            $raw = $info->raw;
            $imgString = substr($raw, strpos($raw, ',') + 1);
            if ($info->format === 'svg') {
                $rawImage = ImageHelper::decodeSvgDataString($imgString);
            } else {
                $rawImage = base64_decode($imgString);
            }
            if ($this->params->get('download')) {
                $sender->sendImage($rawImage, $info->type, 'icinga-img.' . $info->format);
            } else {
                $sender->sendImage($rawImage, $info->type);
            }
        } catch (Exception $e) {
            $sender->sendError($e, $this->getWidth(), $this->getHeight());
        }
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

    protected function preventZfLayout()
    {
        $this->_helper->getHelper('layout')->disableLayout();
        $this->getViewRenderer()->disable();
    }
}
