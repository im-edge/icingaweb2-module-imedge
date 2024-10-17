<?php

namespace Icinga\Module\Imedge\NodeControl;

// For MIBs hook only
use IMEdge\Web\Rpc\IMEdgeClient;

class RemoteSnmpClient extends IMEdgeClient
{
    /**
     * @api
     * Used by MIBS module
     */
    public function walk($oid, $ip, $credentialUuid, $limit = null, $nextOid = null)
    {
        $params = [
            'credentialUuid' => $credentialUuid,
            'address'   => (object) [
                'ip'   => $ip,
                'port' => 161
            ],
            'oid'       => $oid,
            // 'limit'     => $limit,
            // 'nextOid'  => $nextOid,
        ];
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($nextOid) {
            $params['nextOid'] = $nextOid;
        }

        return $this->request('snmp.walk', (object) $params);
    }
}
