<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Mock\ApacheNote;

/**
 * @param string $note_name
 * @param string | null $note_value
 * @return mixed
 */
function apache_note($note_name, $note_value=null)
{
    $apacheNote = ApacheNote::getInstance();

    return (!$note_value) ? $apacheNote->getNote($note_name) : $apacheNote->setNote($note_name, $note_value);
}

?>
