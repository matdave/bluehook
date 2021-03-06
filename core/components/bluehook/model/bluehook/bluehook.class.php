<?php

class BlueHook
{
    /**
     * @var modX|null $modx
     */
    public $modx = null;
    /**
     * @var array
     */
    public $config = array();
    /**
     * @var string
     */
    public $namespace = 'bluehook';
    /**
     * SendInBlue Class
     */
    private $SIB = null;
    /**
     * @var contact|null $this->contact
     */
    public $contact = null;

    public function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        $corePath = $this->modx->getOption('bluehook.core_path', $config, $this->modx->getOption('core_path') . 'components/bluehook/');
        $this->config = array_merge(array(
            'basePath' => $this->modx->getOption('base_path'),
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'snippetPath' => $corePath . 'elements/snippets/',
            'pluginPath' => $corePath . 'elements/plugin/',
        ), $config);
        $this->modxUserId = $this->getOption('use_modx_id', $config, true);
        $this->modx->addPackage('bluehook', $this->config['modelPath']);
        $this->autoload();
    }

    private function autoload()
    {
        require_once $this->config['corePath'].'model/vendor/autoload.php';
        // Configure API key authorization: api-key
        $config = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->getOption('api-key')); 
        $config = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('partner-key', $this->getOption('api-key')); 

        $this->SIB = new SendinBlue\Client\Api\ContactsApi(
            new GuzzleHttp\Client(),
            $config
        );
    } 

    /**
     * @param mixed $field string to compare against)
     * @param array $values array("key1"=>"value1")
     */
    
    public function getField($field = null, $values = array())
    {
        $field = str_replace('[[+', '', $field);
        $field = str_replace('[[!+', '', $field);
        $field = str_replace(']]', '', $field);
        return (array_key_exists($field,$values)) ? $values[$field] : $field;
    } 

    /**
     * @param mixed $fields "key1==property1,key2==property2,property3" or array("key1"=>"property1", "key2"=>"property2")
     * @param array $values array("key1"=>"value1")
     */
    
    public function getProperties($fields = null, $values = array())
    {
        $properties = array();
        if (!is_array($fields)) {
            $fieldsNew = array();
            $fields = explode(',', $fields);
            foreach ($fields as $field) {
                $field = explode('==', $field);
                $fieldsNew[$field[0]] = ($field[1]) ? $field[1] : $field[0];
            }
            $fields = $fieldsNew;
        }
        if (!empty($fields)) {
            foreach ($fields as $k => $v) {
                $properties[$k] = $values[$v];
            }
            return $properties;
        } else {
            return $values;
        }
    } 

    /**
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * @return mixed The option value or the default value specified.
     */
    public function getOption($key, $options = array(), $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->config)) {
                $option = $this->config[$key];
            } elseif (array_key_exists("{$this->namespace}.{$key}", $this->modx->config)) {
                $option = $this->modx->getOption("{$this->namespace}.{$key}");
            }
        }
        return $option;
    }

    public function getContact($email, $create = true) {
        if(empty($email)) return false;
        try { 
            $this->contact = $this->SIB->getContactInfo($email); 
        } catch (Exception $e) {
            $this->modx->log(xPDO::LOG_LEVEL_INFO, '[BlueHook] Exception when calling ContactsApi->getContactInfo: '. $e->getMessage());
        } 
        
        if($this->contact == null || !$this->contact->getId()){
            if($create){
                try {
                    $this->contact = new \SendinBlue\Client\Model\CreateContact();
                    $this->contact->setEmail($email);
                    if($this->contact->valid()){
                        $this->SIB->createContact($this->contact);
                    }
                } catch (Exception $e) {
                    $this->modx->log(xPDO::LOG_LEVEL_INFO, '[BlueHook] Exception when calling ContactsApi->createContact: '. $e->getMessage());
                }
            } else {
                return false;
            }
        } 
        return true;
    }

    public function checkListStatus($email, $listId) {
        if(empty($email) || empty($listId)) return false;
        if(!$this->getContact($email)) return false;
        $listIds = $this->contact->getListIds();
        if(is_array($listIds) && !in_array($listId, $listIds)){
            return false;
        }
        return true;
    }

    public function subscribe($email, $listId, $fields = array(), $doi = 0, $doiRedirectTo = 0) { 
        
        if(empty($email) || empty($listId)) return;
        if(!$this->getContact($email, !boolval($doi))){
            if($doi){
                return $this->doisubscribe($email, $listId, $fields, $doi, $doiRedirectTo);
            }
        } else{ return false; }
        
        if(!empty($fields)){
            try {
                $updateContact = new \SendinBlue\Client\Model\UpdateContact();
                $updateContact->setAttributes($fields);
                $this->SIB->updateContact(urlencode($email), $updateContact);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_INFO, '[BlueHook] Exception when calling ContactsApi->updateContact: '. $e->getMessage());
            }
        }
        
        $listIds = $this->contact->getListIds();
        if(is_array($listIds) && !in_array($listId, $listIds)){
            try {
                $this->SIB->addContactToList($listId, array('emails' => array($email))); 
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_INFO, '[BlueHook] Exception when calling ContactsApi->addContactToList: '. $e->getMessage());
            }
        }
    }

    public function doisubscribe($email, $listId, $fields = array(), $doi = 0, $doiRedirectTo = 0){
        if(empty($email) || empty($listId) || !boolval($doi)) return;
        try {
            $this->contact = new \SendinBlue\Client\Model\CreateDoiContact();
            $this->contact->setEmail($email);
            $this->contact->setAttributes($fields);
            $this->contact->setIncludeListIds(array($listId));
            $this->contact->setTemplateId($doi);
            if($doiRedirectTo){
                $doiRedirectTo = $this->modx->resource->id;
            }
            $this->contact->setRedirectionUrl($this->modx->makeUrl($doiRedirectTo, $this->modx->resource->context_key, '', 'full'));
            if($this->contact->valid()){
                $this->SIB->createDoiContact($this->contact);
            }
        } catch (Exception $e) {
            $this->modx->log(xPDO::LOG_LEVEL_INFO, '[BlueHook] Exception when calling ContactsApi->createContact: '. $e->getMessage());
        } 
    }

    public function unsubscribe($email, $listId) {
        
        if(empty($email) || empty($listId)) return;
        if(!$this->getContact($email, false)) return false; 
        
        $listIds = $this->contact->getListIds();
        if(is_array($listIds) && in_array($listId, $listIds)){
            try {
                $this->SIB->removeContactFromList($listId, array('emails' => array($email))); 
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_INFO, '[BlueHook] Exception when calling ContactsApi->removeContactFromList: '. $e->getMessage());
            }
        }

    }

}