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
        return ($values[$field]) ? $values[$field] : $field;
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
                $properties[$v] = $values[$k];
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

    public function subscribe($email, $listId, $fields = array()) { 
        try {
            $properties = array(
                'email' => $email,
                'attributes' => $fields,
                'listIds' => array($listId),
                'updateEnabled' => true
            ); 
            $this->SIB->createContact($properties);
        } catch (Exception $e) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, '[BlueHook] Exception when calling ContactsApi->getContactInfo: ', $e->getMessage(), PHP_EOL);
        } 
    }

}