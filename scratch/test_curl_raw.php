<?php
$ch = curl_init('https://lookaside.fbsbx.com/foo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
echo curl_exec($ch);
