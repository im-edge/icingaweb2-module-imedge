<?php

namespace Icinga\Module\Imedge\Web\Widget\Measurement;

use Clue\React\Block;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use Icinga\Web\Notification;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\HtmlDocument;
use React\EventLoop\Loop;

class MeasurementFileActions extends HtmlDocument
{
    use TranslationHelper;

    /** @var Url */
    protected $url;

    /** @var string */
    protected $filename;

    public function __construct(Url $url, $filename)
    {
        $this->url = $url;
        $this->filename = $filename;
    }

    protected function assemble()
    {
        $this->add($this->createFileActions());
    }

    protected function createFileActions()
    {
        return [
            Link::create(
                $this->translate('Flush'),
                $this->url->with('flush', true),
                null,
                ['class' => 'icon-reschedule']
            ),
            Link::create(
                $this->translate('Forget'),
                $this->url->with('forget', true),
                null,
                ['class' => 'icon-trash']
            ),
            Link::create(
                $this->translate('Flush and Forget'),
                $this->url->with('flushandforget', true),
                null,
                ['class' => 'icon-spinner']
            ),
            Link::create(
                $this->translate('Delete'),
                $this->url->with('delete', true),
                null,
                ['class' => 'icon-cancel']
            ),
        ];
    }

    public function eventuallyRun(IMEdgeClient $client)
    {
        $filename = $this->filename;
        $params = $this->url->getParams();
        if ($params->get('flush')) {
            if ($this->runAction($client, 'flush')) {
                Notification::success("'$filename' has been flushed");
                return $this->url->without('flush');
            } else {
                Notification::error("Unable to flush '$filename'");
            }
        }
        if ($params->get('forget')) {
            if ($this->runAction($client, 'forget')) {
                Notification::success(
                    "'$filename' has been forgotten (removed from Cache). Data might have been lost."
                );
                return Url::fromPath('imedge/measurement/forgotten', [
                    'filename' => $filename
                ]);
            } else {
                Notification::error("Unable to forget '$filename'");
            }
        }
        if ($params->get('flushandforget')) {
            if ($this->runAction($client, 'flushAndForget')) {
                Notification::success("'$filename' has been flushed and removed from cache");
                return Url::fromPath('imedge/measurement/forgotten', [
                    'filename' => $filename
                ]);
            } else {
                Notification::error("Unable to flush and forget '$filename'");
            }
        }
        if ($params->get('delete')) {
            if ($this->runAction($client, 'delete')) {
                Notification::success("'$filename' has been deleted");
                return Url::fromPath('imedge/measurement/deleted', [
                    'filename' => $filename
                ]);
            } else {
                Notification::error("Unable to delete '$filename'");
            }
        }

        return false;
    }

    protected function runAction(IMEdgeClient $client, $method)
    {
        $file = $this->filename;
        $promise = $client->request('rrd.' . $method, ['file' => $file]);

        return Block\await($promise, Loop::get(), 3);
    }
}
