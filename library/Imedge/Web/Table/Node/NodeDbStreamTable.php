<?php

namespace Icinga\Module\Imedge\Web\Table\Node;

use gipfl\Format\LocalDateFormat;
use gipfl\IcingaWeb2\Link;
use gipfl\Web\Table\NameValueTable;
use Icinga\Date\DateFormatter;
use IMEdge\Web\Rpc\IMEdgeClient;
use IntlChar;
use ipl\Html\Html;
use ipl\Html\Table;
use React\EventLoop\Loop;

use function Clue\React\Block\await;

class NodeDbStreamTable extends Table
{
    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
    ];
    protected array $dataNodeMappings;
    protected array $rows;
    protected ?LocalDateFormat $dateFormatter = null;
    protected ?string $lastDay = null;

    public function __construct(IMEdgeClient $client, array $dataNodeMappings)
    {
        $this->dateFormatter = new LocalDateFormat();
        $rows = await($client->request('node.getDbStream', []), Loop::get(), 10);
        $this->dataNodeMappings = $dataNodeMappings;
        foreach ($rows as $position => $row) {
            $this->add($this->renderRow($position, $row));
        }
    }

    protected function renderRow($position, $row)
    {
        $posParts = explode('-', $position);
        $timestamp = (int) floor($posParts[0] / 1000);
        $this->renderDayIfNew($timestamp);
        /*
        if ($row->value) {
            $values = $row->value;
            foreach ($row->keyProperties as $property) {
                $value = $values->$property;
                // unset($values[$property]);
                if ($value === null) {

                }
            }
            $values = $this->formatValues($values);
        } else {
            $values = '-';
        }
        */
        // TODO: node from stream name
        // $dataNodeHex = Uuid::fromBytes($row->datanode_uuid)->toString();
        return $this::row([
            [
                $this->prepareDummyQuery($row->action, $row->table, $row->keyProperties, (array) $row->value),
                // sprintf(
                //     'Source: %s / %s',
                //     $this->dataNodeMappings[$dataNodeHex] ?? $dataNodeHex,
                //     $position
                // ),
                // Html::tag('br'),
            ],
            DateFormatter::formatTime($timestamp),
        ]);
    }

    protected function prepareDummyQuery(string $action, string $table, array $keyProperties, array $values)
    {
        switch ($action) {
            case 'create':
                return Html::sprintf('INSERT INTO %s %s', $table, $this->prepareDummyValues($values));
            case 'update':
                return Html::sprintf(
                    'UPDATE %s %s %s',
                    $table,
                    $this->prepareDummyValues($this->removeKeyProperties($values, $keyProperties)),
                    $this->prepareDummyWhere($keyProperties, $values)
                );
            case 'delete':
                return Html::sprintf(
                    'DELETE %s %s',
                    $table,
                    $this->prepareDummyWhere($keyProperties, $values)
                );
            default:
                throw new \RuntimeException("Unexpected DB Stream action: $action");
        }
    }

    protected function removeKeyProperties($values, $keys)
    {
        foreach ($keys as $key) {
            unset($values[$key]);
        }

        return $values;
    }

    protected function prepareDummyWhere($keyProperties, $values): string
    {
        $parts = [];
        foreach ($keyProperties as $key) {
            $parts[] = sprintf('%s = %s', $key, $values[$key] ?? '(missing)');
        }

        return 'WHERE ' . implode(', ', $parts);
    }

    protected function prepareDummyValues($values): string
    {
        $parts = [];
        foreach ($values as $key => $value) {
            $parts[] = sprintf('%s = %s', $key, $value);
        }

        return 'SET ' . implode(', ', $parts);
    }

    /**
     * @param  int $timestamp
     */
    protected function renderDayIfNew($timestamp)
    {
        $day = $this->dateFormatter->getFullDay($timestamp);

        if ($this->lastDay !== $day) {
            $this->nextHeader()->add(
                $this::th($day, [
                    'colspan' => 2,
                    'class'   => 'table-header-day'
                ])
            );

            $this->lastDay = $day;
            $this->nextBody();
        }
    }

    protected static function getPrintableString(string $string): string
    {
        if (ctype_print($string)) {
            $value = $string;
        } else {
            $value = '';
            foreach (mb_str_split($string) as $char) {
                if (IntlChar::isprint($char)) {
                    $value .= $char;
                } else {
                    $value .= '\\x' . bin2hex($char);
                }
            }
        }

        return $value;
    }

    // Unused, both:
    protected function formatValues($sentValues): NameValueTable
    {
        $result = new NameValueTable();
        foreach ($sentValues as $key => $value) {
            $result->addNameValueRow($key, $this->formatValue($key, $value));
        }

        return $result;
    }

    protected function formatValue($key, $value)
    {
        if ($value !== null && substr($value, 0, 2) === '0x') {
            $value = self::getPrintableString(hex2bin(substr($value, 2)));
        }
        if ($key === 'uuid' || $key === 'system_uuid' || $key === 'device_uuid') {
            $value = Link::create($value, 'inventory/snmp/device', [
                'uuid' => $value,
            ]);
        }

        return $value;
    }
}
