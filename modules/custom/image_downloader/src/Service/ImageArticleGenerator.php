<?php

namespace Drupal\image_downloader\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use GuzzleHttp\ClientInterface;
use Drupal\node\Entity\Node;

use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\kinomania_film_pages\Filmcard;

class ImageArticleGenerator
{
    private $urlGenerator = null;
    public function __construct(UrlGeneratorInterface $url_generator)
    {
        $this->urlGenerator = $url_generator;
    }

    /**
     * Factory method to inject the URL generator service.
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('url_generator')
        );
    }

    public function getFullUrlFromUri($uri)
    {
        $url = Url::fromUri($uri);
        return $this->urlGenerator->generateFromRoute($url->getRouteName(), $url->getRouteParameters(), ['absolute' => TRUE]);
    }

    private function getPreviewFilm($_node)
    {
        $uri = null;
        $selected_poster = null;
        if ($_node) {
            $max_width = 0;
            $selected_poster = null;

            $paragraphs_field_items = $_node->get('field_film_frames')->getValue();
            $poster_files = [];
            foreach ($paragraphs_field_items as $paragraph_item) {
                $target_id = $paragraph_item['target_id'];
                $paragraph = \Drupal\paragraphs\Entity\Paragraph::load($target_id);
                if ($paragraph) {
                    $field_frame_image = null;
                    $field_frame_preview = null;
                    $field_frame_image = $paragraph->get('field_frame_image')->getValue();
                    $field_frame_preview = $paragraph->get('field_frame_preview')->getValue();

                    if (isset($field_frame_image[0]['target_id'])) {
                        $target_id = $field_frame_image[0]['target_id'];
                        $file = File::load($target_id);
                        if (!empty($file)) {
                            $poster_files[] = $file;
                            if (isset($field_frame_preview[0]['value']) && $field_frame_preview[0]['value']) {
                                $selected_poster = $file;
                            }
                        }
                    }
                }
            }

            if ($poster_files && !$selected_poster) {
                foreach ($poster_files as $poster_file) {
                    if ($poster_file instanceof File) {
                        $image = \Drupal::service('image.factory')->get($poster_file->getFileUri());
                        if ($image->isValid()) {
                            $width = $image->getWidth();
                            if ($width > $max_width) {
                                $max_width = $width;
                                $selected_poster = $poster_file;
                            }
                        }
                    }
                }
            }


            $paragraphs_field_items = $_node->get('field_film_wallpapers')->getValue();
            $poster_files = [];
            foreach ($paragraphs_field_items as $paragraph_item) {
                $target_id = $paragraph_item['target_id'];
                $paragraph = \Drupal\paragraphs\Entity\Paragraph::load($target_id);

                if ($paragraph) {
                    $field_frame_image = null;
                    $field_frame_preview = null;
                    $field_wallpaper_image = $paragraph->get('field_wallpaper_image')->getValue();
                    $field_wallpaper_preview = $paragraph->get('field_wallpaper_preview')->getValue();

                    if (isset($field_wallpaper_image[0]['target_id'])) {
                        $target_id = $field_wallpaper_image[0]['target_id'];
                        $file = File::load($target_id);
                        if (!empty($file)) {
                            $poster_files[] = $file;
                            if (isset($field_wallpaper_preview[0]['value']) && $field_wallpaper_preview[0]['value']) {
                                $selected_poster = $file;
                            }
                        }
                    }
                }
            }
        }

        if ($selected_poster) {
            $uri = $selected_poster->getFileUri();
        }
        return $uri;
    }


    private function getArticleType($node)
    {
        $content_type = $node->getType();
        $article_type = [
            'label' => "Статьи",
            'typeid' => "posts",
        ];

        if ($content_type === 'article') {
            $reference_field = $node->get('field_article_type');
            if (!$reference_field->isEmpty()) {
                $referenced_node = $reference_field->referencedEntities()[0];
                $label = $referenced_node->label();

                $typeid_map = [
                    "Подборки" => "compilations",
                    "Интервью" => "interviews",
                    "Рецензии" => "reviews",
                ];

                $typeid = $typeid_map[$label] ?? "posts";
                $label = isset($typeid_map[$label]) ? $label : "Статьи";

                $article_type = [
                    'label' => $label,
                    'typeid' => $typeid,
                ];
            }
        } elseif ($content_type === 'news') {
            $article_type = [
                'label' => "Моменты",
                'typeid' => "news",
            ];
        }
        return mb_strtolower($article_type['label']);
    }

    private function wrapText($text, $fontSize, $fontPath, $maxWidth)
    {
        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine . ' ' . $word;
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $testLine);
            $lineWidth = $textBox[2] - $textBox[0];

            if ($lineWidth <= $maxWidth) {
                $currentLine = $testLine;
            } else {
                $lines[] = trim($currentLine);
                $currentLine = $word;
            }
        }

        if (!empty($currentLine)) {
            $lines[] = trim($currentLine);
        }

        return $lines;
    }

    public function generateImage($node)
    {
        try {
            if ($node) {
                $image_uri = $this->getImageUri($node);
                if (!$image_uri) {
                    return null;
                }

                $text = $node->label();
                $type = $this->getArticleType($node);
                $fontPath = \Drupal::moduleHandler()->getModule('image_downloader')->getPath() . '/font/stolzl_regular.otf';

                $image = $this->createImageCanvas(800, 420);
                $this->applyBackground($image, $image_uri, 800, 420);
                $this->applyOverlay($image);

                $this->placeWatermarkImage($image);

                $lines = $this->wrapText($text, 22, $fontPath, 720);
                $lastLineY = $this->placeTextOnImage($image, $lines, $fontPath, 22, 1.7);

                $lastLineY = $this->drawSeparatorLine($image, $lastLineY);
                $this->placeWatermarkText($image, $type, $fontPath, 14, $lastLineY);

                $file_url = $this->saveImage($node, $image);

                imagedestroy($image);

                return $file_url;
            } else return null;
        } catch (\Exception $e) {
            \Drupal::logger('image_downloader')->error("Error generating image: @message", ['@message' => $e->getMessage()]);
            return null;
        }
    }

    private function getImageUri($node)
    {
        $uri = null;

        $content_type = $node->getType();
        if ($content_type == "article") {
            $reference_field = $node->get('field_article_type');
            if (!$reference_field->isEmpty()) {
                $referenced_nodes = $reference_field->referencedEntities();
                if (isset($referenced_nodes[0])) {
                    $referenced_node = $referenced_nodes[0];
                    $id = $referenced_node->id();
                    $review_type_id = get_term_by_name("article_types", "Рецензии");
                    if ($id == $review_type_id) {
                        $review_ref_values = $node->get('field_review_ref')->getValue();
                        if (!empty($review_ref_values[0])) {
                            $review_ref = $review_ref_values[0];
                            if (isset($review_ref['target_id'])) {
                                $refnode = Node::load($review_ref['target_id']);
                                $uri = $this->getPreviewFilm($refnode);
                                return $uri;
                            }
                        }
                    }
                }
            }
        }

        $image_field = $node->get('field_image');
        if ($image_field->entity) {
            $uri = $image_field->entity->getFileUri();
        }

        return $uri;
    }

    private function createImageCanvas($width, $height)
    {
        $image = imagecreatetruecolor($width, $height);
        $backgroundColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $backgroundColor);
        return $image;
    }

    private function applyBackground($image, $image_uri, $width, $height)
    {
        if ($image_uri) {
            $files = \Drupal::entityTypeManager()
                ->getStorage('file')
                ->loadByProperties(['uri' => $image_uri]);

            $file = reset($files);

            if ($file instanceof \Drupal\file\Entity\File) {
                $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
                $image_data = file_get_contents($file_path);

                if ($image_data !== false) {
                    $backgroundImage = imagecreatefromstring($image_data);

                    if ($backgroundImage !== false) {
                        $bgWidth = imagesx($backgroundImage);
                        $bgHeight = imagesy($backgroundImage);

                        $aspectRatio = $bgWidth / $bgHeight;
                        $targetAspectRatio = $width / $height;

                        if ($aspectRatio > $targetAspectRatio) {
                            // Crop the width
                            $cropWidth = intval($bgHeight * $targetAspectRatio);
                            $cropHeight = $bgHeight;
                            $cropX = intval(($bgWidth - $cropWidth) / 2);
                            $cropY = 0;
                        } else {
                            // Crop the height
                            $cropWidth = $bgWidth;
                            $cropHeight = intval($bgWidth / $targetAspectRatio);
                            $cropX = 0;
                            $cropY = intval(($bgHeight - $cropHeight) / 2);
                        }

                        $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
                        imagecopy($croppedImage, $backgroundImage, 0, 0, $cropX, $cropY, $cropWidth, $cropHeight);

                        $scaledImage = imagecreatetruecolor($width, $height);
                        imagecopyresampled($scaledImage, $croppedImage, 0, 0, 0, 0, $width, $height, $cropWidth, $cropHeight);

                        imagecopy($image, $scaledImage, 0, 0, 0, 0, $width, $height);

                        imagedestroy($scaledImage);
                        imagedestroy($croppedImage);
                        imagedestroy($backgroundImage);
                    } else {
                        \Drupal::messenger()->addError(t('Не удалось создать изображение из данных.'));
                    }
                } else {
                    \Drupal::messenger()->addError(t('Не удалось загрузить содержимое файла.'));
                }
            } else {
                \Drupal::messenger()->addError(t('Файл с URI @uri не найден.', ['@uri' => $image_uri]));
            }
        }
    }

    private function applyOverlay($image)
    {
        $overlayColor = imagecolorallocatealpha($image, 0, 0, 0, 51);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $overlayColor);
    }

    private function placeTextOnImage($image, $lines, $fontPath, $fontSize, $lineHeight, $rightMargin = 40, $bottomMargin = 5)
    {
        $lines = array_slice($lines, 0, 4);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $totalTextHeight = count($lines) * $fontSize * $lineHeight;
        $yStart = imagesy($image) - $totalTextHeight - $bottomMargin;
        $y = $yStart;

        foreach ($lines as $line) {
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $line);
            $textWidth = $textBox[2] - $textBox[0];
            $x = imagesx($image) - $textWidth - $rightMargin;
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $line);
            $y += intval($fontSize * $lineHeight);
        }

        return $yStart;
    }


    private function drawSeparatorLine($image, $yPosition, $thickness = 1)
    {
        $yPosition = $yPosition - 70;
        $lineColor = imagecolorallocate($image, 255, 255, 255);
        $margin = 763;
        $lineWidth = 97;
        $xStart = $margin;
        $xEnd = $xStart - $lineWidth;

        imagefilledrectangle($image, 763, $yPosition, 666, $yPosition, $lineColor);

        return $yPosition;
    }


    private function placeWatermarkText($image, $type, $fontPath, $fontSize, $y)
    {
        $y = $y - 20;
        $watermarkTextColor = imagecolorallocatealpha($image, 255, 136, 0, 1);

        $letterSpacing = 6;

        $totalWidth = 0;

        $type = mb_strtoupper($type);

        for ($i = 0; $i < mb_strlen($type); $i++) {
            $char = mb_substr($type, $i, 1);
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $char);
            $totalWidth += ($textBox[2] - $textBox[0]) + $letterSpacing;
        }
        $totalWidth -= $letterSpacing;

        $x = imagesx($image) - $totalWidth - 40;

        for ($i = 0; $i < mb_strlen($type); $i++) {
            $char = mb_substr($type, $i, 1); // Получаем текущий символ
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $char);
            $textWidth = $textBox[2] - $textBox[0];

            imagettftext($image, $fontSize, 0, $x, $y, $watermarkTextColor, $fontPath, $char);

            $x += $textWidth + $letterSpacing;
        }
    }



    private function placeWatermarkImage($image)
    {
        $modulePath = \Drupal::moduleHandler()->getModule('image_downloader')->getPath();
        $watermarkImagePath = realpath($modulePath . '/icon/kinomania.png');
        $watermarkImage = imagecreatefrompng($watermarkImagePath);
        imagecopy($image, $watermarkImage, 38, 40, 0, 0, 101, 23);
        imagedestroy($watermarkImage);
    }

    private function saveImage($node, $image)
    {
        $filename = md5($node->id()) . '.png';
        $directory = 'public://article_share';
        $file_system = \Drupal::service('file_system');
        $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
        $file_path = $directory . '/' . $filename;

        ob_start();
        imagepng($image);
        $data = ob_get_clean();

        if (file_put_contents($file_system->realpath($file_path), $data) === false) {
            \Drupal::messenger()->addError(t('Не удалось сохранить изображение.'));
            return false;
        }

        $file = File::create([
            'uri' => $file_path,
            'status' => 1,
        ]);

        try {
            $file->save();

            $file_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());

            return $file_url;
        } catch (\Exception $e) {
            \Drupal::messenger()->addError(t('Ошибка при сохранении изображения: @error', ['@error' => $e->getMessage()]));
            return false;
        }
    }
}
