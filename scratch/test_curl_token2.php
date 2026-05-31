<?php
$url = 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=1496213215146848&source=webhook&ext=1780207585&hash=ARnQE2lC1eM-GFIm6levLUrghdoNW6MpKyXYwMDdZM_u1g';
$token = 'EAAHus061leQBRtAtyJ9EHMZAFTQbx4aj0lcT1H1nM5LwPHtIXfeHiMQ6U4NbGRaJGNTsRiTUkQ9nlMolV3Os3rit2ZA6MlytdaCR3jZAZAEE2A7a07eZCmj4ZCINhapnkGhZBy3ChDi4nZBTF2oEmFfhLebOD34TvPtJk29DCPRbZBJ5SDIaOlOpBtxv7k6SUFgPnJ3Qr5Bi44Po3gIf0pzlWb3oz4iRZCsgZAl0FiIYQ9nEYH5bnuOgafNo0bt6KtvQGunA877SmALC5elN7FaSjqnYGiEf6CIxHZBglqkZD';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $token,
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
));
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $status\nResponse preview: " . substr($res, 0, 100) . "\n";
