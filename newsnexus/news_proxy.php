<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$apiKey = "d15d620024d44c37a227b1a755ef67d8"; // Replace with your actual API key
$query = isset($_GET['q']) ? urlencode($_GET['q']) : 'tesla';

// Cache file path
$cacheFile = "cache/news_cache_" . md5($query) . ".json";
$cacheTime = 86400; // Cache expires in 86,400 seconds (24 hours)

// Check if cached file exists and is still valid
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit;
}

// If cache is expired or doesn't exist, fetch new data
$url = "https://newsapi.org/v2/everything?q={$query}&apiKey={$apiKey}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: NewsNexus/1.0',  // Custom User-Agent
    'Accept: application/json'
]);
$response = curl_exec($ch);
curl_close($ch);

// Validate response and cache if successful
$data = json_decode($response, true);
if ($data && isset($data['articles'])) {
    file_put_contents($cacheFile, $response); // Save to cache
}

echo $response;
?>
