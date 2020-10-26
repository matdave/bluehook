<?php

/** 
 * SendInBlue.FormIt.Hook
 */
$debug = $modx->getOption('bluehookDebug', $hook->formit->config, false);
$corePath = $modx->getOption('bluehook.core_path', null, $modx->getOption('core_path') . 'components/bluehook/');
$bluehook = $modx->getService(
    'bluehook',
    'BlueHook',
    $corePath . 'model/bluehook/',
    array('core_path' => $corePath)
);

if (!($bluehook instanceof BlueHook)) {
    $modx->log(xPDO::LOG_LEVEL_ERROR, '[SendInBlue.FormIt.Hook] Could not load bluehook class.');
    if ($debug) {
        $hook->addError('bluehook', 'Could not load Bluehook class.');
        return false;
    } else {
        return true;
    }
}

$values = $hook->getValues();

// List to add contact to
$listId = $hook->formit->config['bluehookList'];

// Email field to check against
$email = $modx->getOption('bluehookEmail', $hook->formit->config, 'email');

// templateId for Double Opt-in
$doi = (int) $modx->getOption('bluehookDoi', $hook->formit->config, 0);

// templateId for Double Opt-in
$doiRedirectTo = (int) $modx->getOption('bluehookDoiRedirectTo', $hook->formit->config, 0);

// Optin Field for Signup (Set to 1/true if ignoring)
$optin = $hook->formit->config['bluehookOptin'];

// Optional Opt-out field (Useful for account updates or systems where you know the email)
$optout = $hook->formit->config['bluehookOptout'];

// Additional Fields to Track
$fields = $hook->formit->config['bluehookFields'];

//Process if event is a dynamic field
$email = $bluehook->getField($email, $values);
$optin = $bluehook->getField($optin, $values);
$optout = $bluehook->getField($optout, $values);
$fields = $bluehook->getProperties($fields, $values);

if (empty($email)) {
    $modx->log(xPDO::LOG_LEVEL_ERROR, '[SendInBlue.FormIt.Hook] No email specified.');
    if ($debug) {
        $hook->addError('bluehook', 'No email specified.');
        return false;
    } else {
        return true;
    }
}

if (empty($optin) || !$optin){
    if(!empty($optout)){
        $bluehook->unsubscribe($email, $listId);
        return true;
    }else{
        if ($debug) {
            $hook->addError('bluehook', 'Not opted in.');
            return false;
        } else {
            return true;
        }
    }
}

$bluehook->subscribe($email, $listId, $fields, $doi, $doiRedirectTo);

return true;