<?php

function current_language() {
    return 'de';
}

function goodmorning($name) {
    $translations = array(
        'en' => 'Good morning %s',
        'de' => 'Guten Morgen %s',
        'nl' => 'Goedemorgen %s',
    );

    return \sprintf($translations[current_language()], $name);
}

