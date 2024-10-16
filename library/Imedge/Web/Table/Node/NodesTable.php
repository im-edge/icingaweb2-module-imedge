<?php

namespace Icinga\Module\Imedge\Web\Table\Node;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use ipl\Html\Text;
use Ramsey\Uuid\Uuid;
use stdClass;

class NodesTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'label'
    ];
    protected LocalTimeFormat $timeFormatter;
    /** @var object */
    protected $nodeIdentifier;

    public function __construct($db, ?stdClass $nodeIdentifier)
    {
        parent::__construct($db);
        $this->nodeIdentifier = $nodeIdentifier;
        $this->timeFormatter = new LocalTimeFormat();
        $this->dateFormatter = new LocalDateFormat();
        $this->addAttributes(['class' => ['table-with-state', 'nodes-table']]);
    }

    protected function renderRow($row)
    {
        $uuid = Uuid::fromBytes($row->uuid);
        /*
        // Test with sample error:
        $row->db_stream_error = "DB Stream sync error for 9efafc09-f2d7-4619-a3bc-330bc325f59d:"
            . " SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'class' cannot be null\n"
            . "Failed query: INSERT INTO inventory_physical_entity (device_uuid, entity_index,"
            . " description, parent_index, class, relative_position, name, field_replaceable_unit,"
            . " revision_hardware, revision_firmware, revision_software, serial_number, manufacturer_name,"
            . " model_name, alias, asset_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        */
        $niceUuid = $uuid->toString();
        $label = Link::create($row->label, 'imedge/node', [
            'uuid' => $niceUuid
        ]);
        if ($this->nodeIdentifier && $niceUuid === $this->nodeIdentifier->uuid) {
            $label = [
                Icon::create('upload', ['title' => $this->translate('Local node')]),
                ' ',
                $label
            ];
        }
        $tr = $this::row([
            $label,
            $this->formatStreamPosition($row->db_stream_position, $row->db_stream_error),
        ]);
        if ($row->db_stream_error) {
           $tr->addAttributes(['class' => 'state-critical']);
        } else {
            $tr->addAttributes(['class' => 'state-ok']);
        }

        return $tr;
    }

    protected function formatStreamPosition($position, $error): HtmlDocument
    {
        if ($position === '0-0') {
            $result = [$this->translate("DB Stream didn't start yet")];
        } else {
            [$time, $relative] = explode('-', $position, 2);
            $time = $time / 1000;
            $result = [
                $this->timeFormatter->getTime($time),
                ', ',
                $this->dateFormatter->getFullDay($time),
                " ($relative)",
            ];
        }
        if ($error) {
            $result[] = Html::tag('br');
            $result[] = Html::tag('span', ['class' => 'error'], new HtmlString(nl2br((new Text($error))->render())));
        }

        return (new HtmlDocument())->add($result);
    }

    protected function getColumnsToBeRendered(): array
    {
        return [
            $this->translate('Label'),
            $this->translate('DB Stream: last update'),
        ];
    }

    protected function prepareQuery()
    {
        return $this->db()->select()->from('datanode', ['uuid', 'label', 'db_stream_position', 'db_stream_error']);
    }
}
