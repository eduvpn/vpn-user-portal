<?php

$finder = Symfony\CS\Finder::create()
    ->in(__DIR__)
;

return Symfony\CS\Config::create()
    ->fixers(['ordered_use', 'short_array_syntax'])
    ->finder($finder)
;
