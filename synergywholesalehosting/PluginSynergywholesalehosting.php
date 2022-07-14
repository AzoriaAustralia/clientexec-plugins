<?php

require_once 'modules/admin/models/ServerPlugin.php';

class PluginSynergywholesalehosting extends ServerPlugin
{
    public $features = [
        'packageName' => true,
        'testConnection' => true,
        'showNameservers' => true,
        'directlink' => true,
        'upgrades' => true
    ];

    public function getVariables()
    {
        $variables = [
            'Name' => [
                'type' => 'hidden',
                'description' => 'Used by CE to show plugin',
                'value' => 'Synergy Wholesale Hosting'
            ],
            'Description' => [
                'type' => 'hidden',
                'description' => 'Description viewable by admin in server settings',
                'value' => 'Synergy Wholesale Hosting Account Integration'
            ],
            'Business Name' => [
                'type' => 'text',
                'description' => "Name to show in Error's. Example: Bob's Hosting",
                'value' => '',
                'encryptable' => false
            ],
            'Reseller ID' => [
                'type' => 'text',
                'description' => 'Enter your Reseller ID for Synergy Wholesale',
                'value' => '',
                'encryptable' => true
            ],
            'API Key' => [
                'type' => 'text',
                'description' => 'Enter your API Key for Synergy Wholesale',
                'value' => '',
                'encryptable' => true
            ],
            'Actions' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin per server',
                'value' => 'Create,Delete,Suspend,UnSuspend,Upgrade,Recreate,Synchronize'
            ],
            'Registered Actions For Customer' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin per server for customers',
                'value' => 'Recreate,Upgrade'
            ],
            'package_vars' => [
                'type' => 'hidden',
                'description' => 'Whether package settings are set',
                'value' => '1',
            ],
            'package_vars_values' => []
        ];
        return $variables;
    }

    function getProductTypes()
    {
        return [
            'Website'
            // 'Virtual Machine',
            // 'Exchange Organization',
            // 'Exchange Mailbox',
            // 'CSP Customer',
            // 'Skype for Business',
            // 'Microsoft Sharepoint'
        ];
    }

    public function getAvailableActions($userPackage)
    {
        $args = $this->buildParams($userPackage);
        $actions = [];
        $auth = $this->getAuth($args);
        $data = array_merge([
            'identifier' => $userPackage->getCustomField('Server Acct Properties')
        ], $auth);
        $response = $this->call_synergy('hostingGetService', $data);
        CE_Lib::log(4, $args);
        if (in_array($response->status, ['ERR_HOSTINGTERMINATESERVICE_FAILED'], false)) {
            $actions[] = 'Create';
            $actions[] = 'Synchronize';
        } elseif ($response->status = 'Active') {
            $actions[] = 'Delete';
            $actions[] = 'Suspend';
            $actions[] = 'Recreate';
            $actions[] = 'Synchronize';
        } elseif ($response->status = 'Suspended') {
            $actions[] = 'Delete';
            $actions[] = 'UnSuspend';
        } else {
            // Implement a failsafe just in case !
            $actions[] = 'Create';
            $actions[] = 'Synchronize';
            $actions[] = 'Delete';
            $actions[] = 'Suspend';
            $actions[] = 'Recreate';
            $actions[] = 'Synchronize';
            $actions[] = 'Delete';
            $actions[] = 'UnSuspend';
        }
        return $actions;
    }

    public function testConnection($args)
    {
        // Test connection by calling account balance and checking status
        CE_Lib::log(4, 'Testing connection to Synergy Wholesale');
        $auth = $this->getAuth($args);
        $response = $this->call_synergy('balanceQuery', $auth);
        if ($response->status != 'OK') {
            throw new CE_Exception("Invalid Credentials.");
        }
        return;
    }

    // ---------------- Clientexec Functions --------------------

    public function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->delete($args);
        return 'Package has been deleted.';
    }

    public function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->create($args);
        return 'Package has been created.';
    }

    public function doUpdate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->update($args);
        return 'Package has been updated.';
    }

    public function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->suspend($args);
        return 'Package has been suspended.';
    }

    public function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->unsuspend($args);
        return 'Package has been unsuspended.';
    }

    public function doRecreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->recreate($args);
        return 'Package has been recreated.';
    }

    public function doSynchronize($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->synchronize($args);
        return 'Package has been syncronized.';
    }

    // ---------------- Synergy Functions --------------------

    public function create($args)
    {
        // Perform creation of package and throw exception if any errors.
        $userPackage = new UserPackage($args['package']['id']);
        $auth = $this->getAuth($args);
        $data = array_merge([
            'planName' => $args['package']['name_on_server'],
            'domain' => $args['package']['domain_name'],
            'email' => $args['customer']['email']
        ], $auth);
        $response = $this->call_synergy('hostingPurchaseService', $data);
        if (!in_array($response->status, ['OK', 'OK_PENDING'], false)) {
            throw new CE_Exception($this->createError($args));
        } else {
            $userPackage->setCustomField('User Name', $response->username);
            $userPackage->setCustomField('Password', $response->password);
            $userPackage->setCustomField('Domain Name', $response->domain);
            $userPackage->setCustomField('Server Acct Properties', $response->hoid);
        }
    }

    public function delete($args)
    {
        // Perform termination of package and throw exception if any errors.
        $auth = $this->getAuth($args);
        $data = array_merge([
            'identifier' => $args['package']['ServerAcctProperties']
        ], $auth);
        $response = $this->call_synergy('hostingTerminateService', $data);
        if (!in_array($response->status, ['OK', 'OK_PENDING'], false)) {
            throw new CE_Exception($this->deleteError($args));
        }
        return;
    }

    public function suspend($args)
    {
        // Perform suspension of package and throw exception if any errors.
        $auth = $this->getAuth($args);
        $data = array_merge([
            'identifier' => $args['package']['ServerAcctProperties']
        ], $auth);
        $response = $this->call_synergy('hostingSuspendService', $data);
        if (!in_array($response->status, ['OK', 'OK_PENDING'], false)) {
            throw new CE_Exception($this->suspendError($args));
        }
        return;
    }

    public function unsuspend($args)
    {
        // Perform unsuspension of package and throw exception if any errors.
        $auth = $this->getAuth($args);
        $data = array_merge([
            'identifier' => $args['package']['ServerAcctProperties']
        ], $auth);
        $response = $this->call_synergy('hostingUnsuspendService', $data);
        if (!in_array($response->status, ['OK', 'OK_PENDING'], false)) {
            throw new CE_Exception($this->suspendError($args));
        }
        return;
    }

    public function synchronize($args)
    {
        // Perform sync of package and throw exception if any errors.
        // Syncs the following values from Synergy: IP Address, Server hostname, Location, Username & Password (Used for cPanel)
        $userPackage = new UserPackage($args['package']['id']);
        $auth = $this->getAuth($args);
        $data = array_merge([
            'identifier' => $args['package']['ServerAcctProperties']
        ], $auth);
        $response = $this->call_synergy('hostingGetService', $data);
        CE_Lib::log(4, $response);
        $userPackage->setCustomField('Shared', 0);
        $userPackage->setCustomField('IP Address', $response->serverIPAddress);
        $userPackage->setCustomField('Server Hostname', $response->server);
        $userPackage->setCustomField('User Name', $response->username);
        $userPackage->setCustomField('Password', $response->password);
        return;
    }

    public function recreate($args)
    {
        // Recreate package and throw exception if any errors.
        // WARNING: ERASES EVERYTHING ON ACCOUNT @ SYNERGY !!!!
        $auth = $this->getAuth($args);
        $data = array_merge([
            'hoid' => $args['package']['ServerAcctProperties'],
            'newPassword' => 'AUTO'
        ], $auth);
        $response = $this->call_synergy('hostingRecreateService', $data);
        if (!in_array($response->status, ['OK', 'OK_PENDING'], false)) {
            throw new CE_Exception($this->recreateError($args));
        }
        $this->synchronize($args);
        return;
    }

    public function update($args)
    {
        // Lets leave changes to synergy for now.
        // Maybe in v2
    }

    public function getDirectLink($userPackage, $getRealLink = true, $fromAdmin = false)
    {
        CE_Lib::log(4, "getRealLink" . $getRealLink);
        CE_Lib::log(4, "fromAdmin" . $fromAdmin);

        $linkText = 'Login To cPanel';
        $args = $this->buildParams($userPackage);
        $auth = $this->getAuth($args);
        $data = array_merge([
            'identifier' => $args['package']['ServerAcctProperties']
        ], $auth);
        if ($fromAdmin) {
            return [
                'cmd' => 'panellogin',
                'label' => $linkText
            ];
        } elseif ($getRealLink) {
            $response = $this->call_synergy('hostingGetLogin', $data);
            if ($response->status != 'OK') {
                throw new CE_Exception($this->loginError($args));
            }

            return array(
                'link'    => '<li><a target="_blank" href="' . $response->url . '">' . $linkText . '</a></li>',
                'rawlink' =>  $response->url,
                'form'    => ''
            );
        } else {
            $link = 'index.php?fuse=clients&controller=products&action=openpackagedirectlink&packageId=' . $userPackage->getId() . '&sessionHash=' . CE_Lib::getSessionHash();

            return array(
                'link' => '<li><a target="_blank" href="' . $link .  '">' . $linkText . '</a></li>',
                'form' => ''
            );
        }
    }

    public function dopanellogin($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $response = $this->getDirectLink($userPackage);
        return $response['rawlink'];
    }

    private function getAuth($args)
    {
        $auth = [
            'resellerID' => $args['server']['variables']['plugin_synergywholesalehosting_Reseller_ID'],
            'apiKey' => $args['server']['variables']['plugin_synergywholesalehosting_API_Key']
        ];

        return $auth;
    }

    public function call_synergy($action, $data)
    {
        try {
            $client = new \SoapClient(
                null,
                [
                    'location' => 'https://api.synergywholesale.com',
                    'uri' => '',
                    'trace' => true,
                    'exceptions' => true
                ]
            );
            $result = $client->$action($data);
        } catch (Exception $e) {
            CE_Lib::log(4, 'API Call Failed: ' . $e);
        }
        return $result;
    }

    // ---------------- Error Functions --------------------

    public function createError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in Creation. Contact " . $businessName . ".";
    }
    public function deleteError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in Deletion. Contact " . $businessName . ".";
    }
    public function suspendError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in Suspension/UnSuspension. Contact" . $businessName . ".";
    }
    public function loginError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in Login. Contact " . $businessName . ".";
    }
    public function recreateError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in ReCreation. Contact " . $businessName . ".";
    }
    public function syncError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in Synchronization. Contact " . $businessName . ".";
    }
    public function updateError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in Update. Contact " . $businessName . ".";
    }
    public function apiError($args)
    {
        $businessName = $args['server']['variables']['plugin_synergywholesalehosting_Business_Name'];
        return "Error in API Call. Contact " . $businessName . ".";
    }
}
