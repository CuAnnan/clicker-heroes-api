<?php
include 'ClickerHeroes.php';

$client = new ClickerHeroes();
echo $client->loadFromPasteBin('http://pastebin.com/WLbfZShj')->asJSON();
