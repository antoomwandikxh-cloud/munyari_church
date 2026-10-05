<?php
$c = file_get_contents('script.js');

// Fix the encoding issue with the checkmark
$c = preg_replace('/b\.textContent = \'. Filled\';/', 'b.textContent = \'\u2713 Filled\';', $c);

file_put_contents('script.js', $c);
echo "Fixed checkmark encoding\n";
?>
