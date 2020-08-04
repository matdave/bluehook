<?php

/** 
 * SendInBlue.checkList
 */
$debug = $modx->getOption('bluehookDebug', $scriptProperties, false);
$corePath = $modx->getOption('bluehook.core_path', null, $modx->getOption('core_path') . 'components/bluehook/');
$bluehook = $modx->getService(
    'bluehook',
    'BlueHook',
    $corePath . 'model/bluehook/',
    array('core_path' => $corePath)
);

if (!($bluehook instanceof BlueHook)) {
    $modx->log(xPDO::LOG_LEVEL_ERROR, '[SendInBlue.checkList] Could not load bluehook class.');
    if ($debug) {
        $hook->addError('bluehook', 'Could not load Segment class.');
        return false;
    } else {
        return true;
    }
}
// List to add contact to
$listId = $modx->getOption('list', $scriptProperties, null);

// Email field to check against
$email = $modx->getOption('email', $scriptProperties, null);

// Return Templates
$inListTpl = $modx->getOption('inListTpl', $scriptProperties, null);
$notInListTpl = $modx->getOption('notInListTpl', $scriptProperties, null);

if($bluehook->checkListStatus($email, $listId)){
    return ($inListTpl) ? $modx->getChunk($inListTpl) : true;
}else{
    return ($notInListTpl) ? $modx->getChunk($notInListTpl) : false;
}