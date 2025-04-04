<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Discovery\DiscoveryRuleImplementation;
use Icinga\Module\Imedge\Web\Form\Discovery\DiscoveryRuleForm;
use Icinga\Module\Imedge\Web\Form\Discovery\DropDiscoveryResultsForm;
use Icinga\Module\Imedge\Web\Form\Discovery\StopDiscoveryJobForm;
use Icinga\Module\Imedge\Web\Form\Discovery\TriggerDiscoveryForm;
use Icinga\Module\Imedge\Web\Form\Filter\NodeFilterForm;
use Icinga\Module\Imedge\Web\Form\Rpc\LiveSnmpScenarioForm;
use Icinga\Module\Imedge\Web\Form\UuidObjectForm;
use Icinga\Module\Imedge\Web\Table\Discovery\DiscoverResultsTable;
use Icinga\Module\Imedge\Web\Table\Discovery\DiscoveryCandidatesTable;
use Icinga\Module\Imedge\Web\Table\Discovery\DiscoveryJobsTable;
use Icinga\Module\Imedge\Web\Table\Discovery\DiscoveryRulesTable;
use Icinga\Module\Imedge\Web\Table\Snmp\SnmpSysInfoDetailTable;
use Icinga\Module\Imedge\Web\Widget\Rpc\LiveSnmpResult;
use IMEdge\Web\Data\Model\DiscoveryCandidate;
use IMEdge\Web\Data\Model\DiscoveryRule;
use IMEdge\Web\Data\Model\SnmpSystemInfo;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\Loop;

use function Clue\React\Block\await;

class DiscoveryController extends CompatController
{
    use DbTrait;

    public function candidatesAction()
    {
        $this->getListTabs()->activate('candidates');
        $db = $this->db();
        $this->addTitle($this->translate('Discovery Candidates'));
        $form = new NodeFilterForm($db);
        $form->setAction((string) $this->getOriginalUrl()->without('datanode'));
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        $table = new DiscoveryCandidatesTable($db);
        if ($uuid = $form->getUuid()) {
            $table->getQuery()->where('d.uuid = ?', $uuid->getBytes());
        }
        $table->renderTo($this);
    }

    public function rulesAction()
    {
        $this->getListTabs()->activate('rules');
        $db = $this->db();
        $this->addTitle($this->translate('Discovery Rules'));
        $this->actions()->add(Link::create($this->translate('Create'), 'imedge/discovery/rule', null, [
            'class' => 'icon-plus',
            'data-base-target' => '_next',
        ]));
        $table = new DiscoveryRulesTable($db);
        $table->renderTo($this);
    }

    public function ruleAction()
    {
        $this->addSingleTab($this->translate('SNMP Discovery Rule'));
        if ($uuid = $this->params->get('uuid')) {
            $uuid = Uuid::fromString($uuid);
        }
        $form = new DiscoveryRuleForm($this->dbStore(), $uuid);
        if ($uuid) {
            $rule = DiscoveryRule::load($this->dbStore(), $uuid);
            $this->addTitle($rule->get('label'));
        } else {
            $this->addTitle($this->translate('Create a new Discovery Rule'));
        }
        $this->handleUuidForm($form);
        $this->content()->add($form);
        if ($uuid) {
            try {
                $this->content()->add($this->ruleCandidatesPreview($form->createInstance()));
            } catch (\Throwable $e) {
                $this->content()->add(Hint::error($e->getMessage()));
            }
        }
    }

    protected function ruleCandidatesPreview(DiscoveryRuleImplementation $instance): HtmlElement
    {
        $count = 0;
        $p = Html::tag('p');
        $p->add([Html::tag('strong', $this->translate('Candidates')), ': ']);
        foreach ($instance->getCandidates() as $candidate) {
            $count++;
            if ($count > 50) {
                $p->add(' ' . $this->translate('and more'));
                break;
            }
            if ($count !== 1) {
                $p->add(', ');
            }
            $p->add($candidate);
        }

        return $p;
    }

    public function jobsAction(): void
    {
        $this->getListTabs()->activate('jobs');
        $this->addTitle($this->translate('Scan Jobs'));

        $form = new NodeFilterForm($this->db());
        $form->setAction((string) $this->getOriginalUrl()->without('datanode'));
        $form->handleRequest($this->getServerRequest());
        $nodeUuid = $form->getUuid();
        $this->content()->add($form);

        if ($nodeUuid === null) {
            $this->content()->add(
                Hint::info($this->translate('Please select an IMEdge node'))
            );
            return;
        }
        $this->setAutorefreshInterval(5);
        $urlParams = [
            'node' => $nodeUuid->toString(),
        ];

        /** @var Link[] $actions */
        $this->actions()->add([
            Link::create($this->translate('Run a Job'), 'imedge/discovery/job', $urlParams + [
                'action' => 'run'
            ], [
                'data-base-target' => '_next',
                'class' => 'icon-right-dir'
            ]),
            Link::create($this->translate('Schedule'), 'imedge/discovery/job', $urlParams + [
                'action' => 'schedule'
            ], [
                'data-base-target' => '_next',
                'class' => 'icon-reschedule'
            ])
        ]);

        $jobs = $this->fetchDiscoveryJobs($nodeUuid);
        if (empty($jobs)) {
            $this->content()->add(
                Hint::info($this->translate('There a no active or finished discovery jobs on this node'))
            );
            return;
        }
        $table = new DiscoveryJobsTable($nodeUuid, $jobs);
        if ($table->count() > 0) {
            $this->content()->add(
                Hint::info($this->translate('Please remove completed Discovery Jobs, once you no longer need them'))
            );
        } else {
            $this->content()->add(
                Hint::info($this->translate('Discovery Jobs can be triggered on-demand right here or via CLI command'))
            );
        }
        $this->content()->add($table);
        $this->setAutorefreshInterval($table->hasRunningJobs() ? 1 : 10);
    }

    protected function fetchDiscoveryJobs(UuidInterface $nodeUuid): array
    {
        $client = (new IMEdgeClient())->withTarget($nodeUuid->toString());
        return (array) await($client->request('snmp.getDiscoveryJobs'));
    }

    public function jobAction()
    {
        if ($nodeUuid = $this->params->get('node')) {
            $nodeUuid = Uuid::fromString($nodeUuid);
        }
        $this->addSingleTab($this->translate('Scan Job'));
        switch ($this->params->get('action')) {
            case 'run':
                $this->addTitle($this->translate('Run a Scan Job'));
                $form = new TriggerDiscoveryForm($nodeUuid, $this->dbStore());
                $form->on($form::ON_SUCCESS, function (TriggerDiscoveryForm $form) use ($nodeUuid) {
                    $this->redirectNow(Url::fromPath('imedge/discovery/jobs#__CLOSE__'));
                });
                $form->handleRequest($this->getServerRequest());
                $this->content()->add($form);
                if ($form->ruleImplementation) {
                    $this->content()->add($this->ruleCandidatesPreview($form->ruleImplementation));
                }
                break;
            case 'schedule':
                $this->addTitle($this->translate('Schedule a Scan Job'));
                $this->content()->add(
                    Hint::error($this->translate('Discovery Job scheduling has not been implemented (yet)'))
                );
                break;
            case 'drop':
                $jobId = $this->requireJobId();
                $dropForm = new DropDiscoveryResultsForm($nodeUuid, $jobId);
                $dropForm->on($dropForm::ON_SUCCESS, function () {
                    $this->redirectNow(Url::fromPath('imedge/discovery/jobs#__CLOSE__'));
                });
                $dropForm->handleRequest($this->getServerRequest());
                $this->content()->add($dropForm);
                break;
            case 'stop':
                $jobId = $this->requireJobId();
                $stopForm = new StopDiscoveryJobForm($nodeUuid, $jobId);
                $stopForm->on($stopForm::ON_SUCCESS, function () {
                    $this->redirectNow(Url::fromPath('imedge/discovery/jobs#__CLOSE__'));
                });
                $stopForm->handleRequest($this->getServerRequest());
                $this->content()->add($stopForm);
                break;
            default:
                $jobId = $this->requireJobId();
                $jobs = $this->fetchDiscoveryJobs($nodeUuid);
                $job = $jobs[$jobId] ?? null;

                $this->actions()->add(
                    Link::create($this->translate('Drop'), $this->url()->with('action', 'drop'), null, [
                        'class' => 'icon-cancel'
                    ]),
                );
                if ($job && $job->status === 'running') {
                    $this->actions()->add(
                        Link::create($this->translate('Stop'), $this->url()->with('action', 'stop'), null, [
                            'class' => 'icon-cancel'
                        ]),
                    );
                }
                $client = (new IMEdgeClient())->withTarget($nodeUuid->toString());
                $results = (array) await($client->request('snmp.getDiscoveryJobResults', [$jobId]));
                $table = new DiscoverResultsTable($this->db(), $nodeUuid, $results);
                $this->content()->add($table);
        }
    }

    protected function requireJobId(): int
    {
        $jobId = $this->params->get('job');
        if ($jobId) {
            return (int) $jobId;
        }

        throw new \RuntimeException('"job" is required');
    }

    protected function handleUuidForm(UuidObjectForm $form): void
    {
        $form->on(
            $form::ON_SUCCESS,
            fn () => $this->redirectNow($this->url()->with('uuid', $form->getUuid()->toString()))
        );

        $form->handleRequest($this->getServerRequest());
    }

    public function candidateAction()
    {
        $this->addSingleTab($this->translate('SNMP Discovery Candidate'));
        $uuid = Uuid::fromString($this->params->getRequired('uuid'));
        $candidate = DiscoveryCandidate::load($this->dbStore(), $uuid);

        $this->addTitle(inet_ntop($candidate->get('ip_address')) . ':' . $candidate->get('snmp_port'));
        if ($sysInfo = $this->loadSysInfo($uuid)) {
            $this->content()->add(new SnmpSysInfoDetailTable($sysInfo));
        }

        $form = new LiveSnmpScenarioForm();
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        $scenarioName = $form->getValue('scenarioName');
        if ($scenarioName === null) {
            return;
        }
        try {
            $client = (new IMEdgeClient())->withTarget(Uuid::fromBytes($candidate->get('datanode_uuid'))->toString());
            $result = await($client->request('snmp.scenario', [
                'credentialUuid' => Uuid::fromBytes($candidate->get('credential_uuid')),
                'address'        => [
                    'ip'   => inet_ntop($candidate->get('ip_address')),
                    'port' => $candidate->get('snmp_port')
                ],
                'name'           => $scenarioName,
                'deviceUuid'     => null,
            ]), Loop::get(), 30);
            $this->content()->add(new LiveSnmpResult($scenarioName, $result, $this->db()));
        } catch (\Exception $e) {
            $this->content()->add(Hint::error($e->getMessage()));
        }
    }

    protected function loadSysInfo(UuidInterface $uuid): ?SnmpSystemInfo
    {
        $store = $this->dbStore();
        if ($store->exists(SnmpSystemInfo::create(['uuid' => $uuid]))) {
            return SnmpSystemInfo::load(
                $this->dbStore(),
                $uuid
            );
        }

        return null;
    }

    protected function getListTabs(): Tabs
    {
        $this->controls()->addAttributes([
            'data-base-target' => '_main'
        ]);
        return $this->tabs()->add('jobs', [
            'label' => $this->translate('Current Discovery Jobs'),
            'url'   => 'imedge/discovery/jobs',
        ])->add('rules', [
            'label' => $this->translate('Discovery Rules'),
            'url'   => 'imedge/discovery/rules',
        ])/*->add('candidates', [
            'label' => $this->translate('Candidates'),
            'url'   => 'imedge/discovery/candidates',
        ])*/;
    }
}
