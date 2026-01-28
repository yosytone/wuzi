<?php

namespace Drupal\image_downloader\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\image_downloader\Service\ImageDownloader;

class DownloadImageForm extends FormBase {

  protected $imageDownloader;

  public function __construct(ImageDownloader $image_downloader) {
    $this->imageDownloader = $image_downloader;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('image_downloader.service')
    );
  }

  public function getFormId() {
    return 'download_image_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image URL'),
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download Image'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('image_url');
    $file = $this->imageDownloader->downloadImage($url);

    if ($file) {
      $this->messenger()->addMessage($this->t($file->getFileUri()));
    } else {
      $this->messenger()->addError($this->t('Failed to download image.'));
    }
  }
}
