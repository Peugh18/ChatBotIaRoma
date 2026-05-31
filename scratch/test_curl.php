<?php
$url = 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=1496213215146848&source=webhook&ext=1780207585&hash=ARnQE2lC1eM-GFIm6levLUrghdoNW6MpKyXYwMDdZM_u1g';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $status\nResponse: $res\n";
