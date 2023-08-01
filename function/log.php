<?php

function create_log(){
    $path_to_plugin = "../wp-content/plugins/SMT-GeCoAI/log/";
    $myfile = fopen($path_to_plugin . "response.json", "a") or die("Unable to open file!");

    


    $txt = $hasil;
    fwrite($myfile, $txt);
    fclose($myfile);
}