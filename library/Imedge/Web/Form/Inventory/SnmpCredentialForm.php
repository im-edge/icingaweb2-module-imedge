<?php

namespace Icinga\Module\Imedge\Web\Form\Inventory;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Imedge\Web\Form\UuidObjectForm;
use IMEdge\Web\Data\Model\SnmpCredential;

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

        $this->addElement('select', 'auth_protocol', [
            'label'        => $this->translate('Authentication protocol'),
            'required'     => true,
            'multiOptions' => [
                null  => t('- Please choose -'),
                'md5'    => 'MD5 (RFC 3414, required)',
                'sha1'   => 'SHA-1 (RFC 3414, optional)',
                'sha224' => 'SHA-224 (RFC 7860)',
                'sha256' => 'SHA-256 (RFC 7860)',
                'sha384' => 'SHA-384 (RFC 7860)',
                'sha512' => 'SHA-512 (RFC 7860)',
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
        $this->addElement('select', 'priv_protocol', [
            'label'        => $this->translate('Privacy protocol'),
            'required'     => true,
            'multiOptions' => [
                null  => t('- Please choose -'),
                'des'    => 'DES (RFC 3826)',
                '3des'   => '3-DES (Cisco)',
                'aes128' => 'AES-128 (Cisco, others)',
                'aes192' => 'AES-192 (Cisco, others)',
                'aes256' => 'AES-256 (Cisco, others)',
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
