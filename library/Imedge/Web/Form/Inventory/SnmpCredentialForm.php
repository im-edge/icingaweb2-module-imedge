<?php

namespace Icinga\Module\Imedge\Web\Form\Inventory;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Web\Form\UuidObjectForm;
use IMEdge\Web\Data\Model\SnmpCredential;
use ipl\Html\Html;

class SnmpCredentialForm extends UuidObjectForm
{
    use TranslationHelper;

    protected const UNCHANGED_PASSWORD = '___UNCHANGED___';
    protected string $modelClass = SnmpCredential::class;
    protected $keyProperty = 'credential_uuid';

    protected function assemble()
    {
        $this->addFormElements();
        $this->addButtons();
    }

    protected function addFormElements()
    {
        $this->addElement('text', 'credential_name', [
            'label' => $this->translate('Credential name'),
            'required' => true,
            'description' => $this->translate('Identifier for this SNMP credential')
        ]);
        $this->addElement('select', 'snmp_version', [
            'label'        => $this->translate('SNMP Version'),
            'required'     => true,
            'value'        => '2c',
            'multiOptions' => [
                '3'  => '3',
                '2c' => '2c',
                '1'  => '1',
            ],
            'class' => 'autosubmit'
        ]);
        if ($this->getValue('snmp_version') !== '3') {
            $this->addElement('password', 'security_name', [
                'label' => $this->translate('Community'),
                'required' => true,
                'description' => $this->translate('SNMPv1/v2c community string (cleartext)')
            ]);
            return;
        }

        // TODO: 32 character limit for the username!
        $this->addElement('text', 'security_name', [
            'label' => $this->translate('Security name'),
            'required' => true,
            'description' => $this->translate('This is your SNMPv3 user')
        ]);
        $this->addElement('checkbox', 'use_auth', [
            'label' => $this->translate('Use authentication'),
            'required' => true,
            'description' => $this->translate('Whether to use an authentication algorithm with a secure passphrase'),
            'class' => 'autosubmit'
        ]);
        if ($this->getValue('use_auth') !== 'y') {
            // TODO: Hidden, to preserve values when toggling:
            // $this->addHidden('auth_protocol');
            // Requires getValues()-cleanup
            return;
        }
        $this->add(Hint::info([
            Html::tag('strong', $this->translate('Available Authentication Methods')),
            Html::tag('ul', [
                Html::tag('li', Html::sprintf('MD5: required in %s', Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc3414',
                    'target' => '_blank'
                ], 'RFC 3414'))),
                Html::tag('li', Html::sprintf('SHA (SHA1): optional in %s', Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc3414',
                    'target' => '_blank'
                ], 'RFC 3414'))),
                Html::tag('li', Html::sprintf('SHA-224: optional in %s with 128bit HMAC', Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc7860',
                    'target' => '_blank'
                ], 'RFC 7860'))),
                Html::tag('li', Html::sprintf('SHA-256: required in %s with 192bit HMAC', Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc7860',
                    'target' => '_blank'
                ], 'RFC 7860'))),
                Html::tag('li', Html::sprintf('SHA-384: optional in %s with 256bit HMAC', Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc7860',
                    'target' => '_blank'
                ], 'RFC 7860'))),
                Html::tag('li', Html::sprintf('SHA-512: suggested in %s with 384bit HMAC', Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc7860',
                    'target' => '_blank'
                ], 'RFC 7860'))),
            ]),
        ]));

        $this->addElement('select', 'auth_protocol', [
            'label'        => $this->translate('Authentication protocol'),
            'required'     => true,
            'multiOptions' => [
                null  => t('- Please choose -'),
                'md5'    => $this->translate('MD5 (required in RFC 3414)'),
                'sha1'   => $this->translate('SHA-1 (optional RFC 3414)'),
                'sha224' => $this->translate('SHA-224 (optional in RFC 7860)'),
                'sha256' => $this->translate('SHA-256 (required in RFC 7860)'),
                'sha384' => $this->translate('SHA-384 (optional in RFC 7860)'),
                'sha512' => $this->translate('SHA-512 (suggested RFC 7860)'),
            ],
        ]);
        $this->addElement('password', 'auth_key', [
            'label' => $this->translate('Authentication passphrase'),
            // 'required' => true,
            'placeholder' => '(Unchanged)',
            'description' => $this->translate(
                'This could be a passphrase (should be at least 8 characters long) or a key (0x...)'
            )
        ]);
        $this->addElement('checkbox', 'use_priv', [
            'label' => $this->translate('Use privacy / encryption'),
            'required' => true,
            'description' => $this->translate('Whether to encrypt your SNMP communication'),
            'class' => 'autosubmit'
        ]);
        if ($this->getValue('use_priv') !== 'y') {
            return;
        }
        $this->add(Hint::info([
            Html::tag('strong', $this->translate('Available Privacy / Encryption Methods')),
            Html::tag('ul', [
                Html::tag('li', Html::sprintf($this->translate('DES: in CBC mode, defined in %s'), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc3414',
                    'target' => '_blank'
                ], 'RFC 3414'))),
                Html::tag('li', Html::sprintf($this->translate(
                    '3-DES: Triple-DES EDE in "Outside" CBC mode, mostly used by Cisco, defined in %s'
                ), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/draft-reeder-snmpv3-usm-3desede-00',
                    'target' => '_blank'
                ], 'draft-reeder-snmpv3-usm-3desede-00'))),
                Html::tag('li', Html::sprintf($this->translate(
                    'AES-128: CFB mode, required in %s with "Blumenthal" Key localization'
                ), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/rfc3826',
                    'target' => '_blank'
                ], 'RFC 3826'))),
                Html::tag('li', Html::sprintf($this->translate(
                    'AES-192: CFB mode, as of %s with "Blumenthal" Key localization'
                ), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/draft-blumenthal-aes-usm-04',
                    'target' => '_blank'
                ], 'draft-blumenthal-aes-usm-04'))),
                Html::tag('li', Html::sprintf($this->translate(
                    'AES-192-C: AES-192 in CFB mode, "Cisco variant", as of %s with "Reeder" Key localization'
                ), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/draft-reeder-snmpv3-usm-3desede-00',
                    'target' => '_blank'
                ], 'draft-reeder-snmpv3-usm-3desede-00'))),
                Html::tag('li', Html::sprintf($this->translate(
                    'AES-256: CFB mode, as of %s with "Blumenthal" Key localization'
                ), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/draft-blumenthal-aes-usm-04',
                    'target' => '_blank'
                ], 'draft-blumenthal-aes-usm-04'))),
                Html::tag('li', Html::sprintf($this->translate(
                    'AES-256-C: AES-256 in CFB mode, "Cisco variant", as of %s with "Reeder" Key localization'
                ), Html::tag('a', [
                    'href' => 'https://datatracker.ietf.org/doc/html/draft-reeder-snmpv3-usm-3desede-00',
                    'target' => '_blank'
                ], 'draft-reeder-snmpv3-usm-3desede-00'))),
            ]),
        ]));
        $this->addElement('select', 'priv_protocol', [
            'label'        => $this->translate('Privacy protocol'),
            'required'     => true,
            'multiOptions' => [
                null  => t('- Please choose -'),
                'des'     => $this->translate('DES (RFC 3414)'),
                'des3'    => $this->translate('3-DES (Cisco, others: Reeder draft)'),
                'aes128'  => $this->translate('AES-128 (RFC 3826)'),
                'aes192'  => $this->translate('AES-192 (Blumenthal draft, AGENT++, like in RFC 3826)'),
                'aes192c' => $this->translate('AES-192-C (Cisco, others: Reeder draft)'),
                'aes256'  => $this->translate('AES-256 (Blumenthal draft, AGENT++, like in RFC 3826)'),
                'aes256c' => $this->translate('AES-256-C (Cisco, others: Reeder draft)'),
            ],
        ]);
        $this->addElement('password', 'priv_key', [
            'label' => $this->translate('Privacy passphrase'),
            'required' => true,
            'renderPassword' => true,
            'description' => $this->translate(
                'This could be a passphrase (should be at least 8 characters long) or a key (0x...)'
            )
        ]);
    }

    protected function getObjectLabel()
    {
        if ($this->hasElement('credential_name')) {
            return $this->getElementValue('credential_name', $this->translate('A new SNMP Credential'));
        }

        return $this->translate('SNMP Credential');
    }

    public function onSuccess()
    {
        $values = $this->getValues();

        // These are not to be stored, we just need them for the security_level
        if (isset($values['use_auth'])) {
            $auth = $values['use_auth'];
            unset($values['use_auth']);
        } else {
            $auth = null;
        }

        if (isset($values['use_priv'])) {
            $priv = $values['use_priv'];
            unset($values['use_priv']);
        } else {
            $priv = null;
        }

        if ($values['snmp_version'] === '3') {
            if ($priv && $auth) {
                $values['security_level'] = 'authPriv';
            } elseif ($auth) {
                $values['security_level'] = 'authNoPriv';
            } else {
                $values['security_level'] = 'noAuthNoPriv';
            }
        } else {
            $values['security_level'] = 'noAuthNoPriv';
        }

        switch ($values['security_level']) {
            case 'noAuthNoPriv':
                unset($values['auth_protocol']);
                unset($values['auth_key']);
                // Intentional fall through
            case 'authNoPriv':
                unset($values['priv_protocol']);
                unset($values['priv_key']);
                break;
        }

        if ($priv && $values['auth_key'] === self::UNCHANGED_PASSWORD) {
            unset($values['auth_key']);
        }

        if ($priv && $values['priv_key'] === self::UNCHANGED_PASSWORD) {
            unset($values['priv_key']);
        }

        $this->succeedWithValues($values);
    }
}
