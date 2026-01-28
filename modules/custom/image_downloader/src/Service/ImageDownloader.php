<?php

namespace Drupal\image_downloader\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;


class ImageDownloader
{

  protected $httpClient;
  protected $fileSystem;

  public function __construct(ClientInterface $http_client, FileSystemInterface $file_system)
  {
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
  }

  public function downloadImage($url)
  {
    try {
      \Drupal::logger('LOL')->error('Download failed: @message', ['@message' => $url]);
      $response = $this->httpClient->get($url, [
          'headers' => [
              'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
              'Referer' => 'https://upakmarket.com/',
              'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
              'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
              'Accept-Encoding' => 'gzip, deflate, br',
              'Connection' => 'keep-alive',
          ],
          'timeout' => 15,
          'verify' => false,
      ]);
      $image_data = $response->getBody();

      $filename = md5($url) . '_' . basename($url);

      // Устанавливаем директорию по умолчанию
      $directory = 'public://imported_images'; // ← КЛЮЧЕВОЕ ИЗМЕНЕНИЕ

      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $file_path = $directory . '/' . $filename;

      file_put_contents($file_path, $image_data);

      $file = File::create([
        'uri' => $file_path,
        'status' => 1,
      ]);
      $file->save();

      return $file;
    } catch (\Exception $e) {
      \Drupal::logger('image_downloader')->error('Download failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  public function downloadImagetoDir($url, $dir = 'imported_images')
  {
    try {
      $response = $this->httpClient->get($url);
      $image_data = $response->getBody();

      $filename = md5($url) . '_' . basename($url);

      // Используем $dir или значение по умолчанию
      $directory = 'public://' . ($dir ?: 'imported_images');

      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $file_path = $directory . '/' . $filename;

      file_put_contents($file_path, $image_data);

      $file = File::create([
        'uri' => $file_path,
        'status' => 1,
      ]);
      $file->save();

      \Drupal::logger('image_downloader')->error('Download failed: @message', ['@message' => $file->getFileUri()]);
      

      return $file;
    } catch (\Exception $e) {
      \Drupal::logger('image_downloader')->error('Download failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  public function downloadImagesToDirArray(array $urls, $dir)
  {
    $client = $this->httpClient; // Используемый Guzzle клиент
    $promises = [];
    $results = [];
    

    if (!empty($dir)) {
        $directory = 'public://' . $dir;
    }
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    
    foreach ($urls as $url) {
        $promises[$url] = $client->getAsync($url);
    }

    // Выполняем запросы параллельно
    $responses = Utils::settle($promises)->wait();

    foreach ($responses as $url => $response) {
        try {
            if ($response['state'] === 'fulfilled') {
                $image_data = $response['value']->getBody();
                $filename = md5($url) . '_' . basename($url);
                $file_path = $directory . '/' . $filename;
                file_put_contents($file_path, $image_data);
                
                $file = File::create([
                    'uri' => $file_path,
                    'status' => 1,
                ]);
                $file->save();

                $results[] = $file;
            } else {

                \Drupal::logger('image_downloader')->error('Failed to download {url}: {error}', [
                    'url' => $url,
                    'error' => $response['reason']->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            watchdog_exception('image_downloader', $e);
        }
    }

    return $results;
  }
}
