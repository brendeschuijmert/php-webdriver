<?php
// Copyright 2004-present Facebook. All Rights Reserved.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

class FirefoxProfile {

  /**
   * @var array
   */
  private $preferences = array();

  /**
   * @var array
   */
  private $extensions = array();

  /**
   * @param string $extension The path to the xpi extension.
   */
  public function addExtension($extension) {
    $this->extensions[] = $extension;
    return $this;
  }

  /**
   * @param string $key
   * @param string|bool|int $value
   * @return FirefoxProfile
   */
  public function setPreference($key, $value) {
    if (is_string($value)) {
      $value = sprintf('"%s"', $value);
    } else if (is_int($value)) {
      $value = sprintf('%d', $value);
    } else if (is_bool($value)) {
      $value = $value ? 'true' : 'false';
    } else {
      throw new WebDriverException(
        'The value of the preference should be either a string, int or bool.');
    }
    $this->preferences[$key] = $value;
    return $this;
  }

  /**
   * @return string
   */
  public function encode() {
    $temp_dir = $this->createTempDirectory('WebDriverFirefoxProfile');

    foreach ($this->extensions as $extension) {
      $this->installExtension($extension, $temp_dir);
    }

    $content = "";
    foreach ($this->preferences as $key => $value) {
      $content .= sprintf("user_pref(\"%s\", %s);\n", $key, $value);
    }
    file_put_contents($temp_dir.'/user.js', $content);

    $zip = new ZipArchive();
    $temp_zip = tempnam('', 'WebDriverFirefoxProfileZip');
    $zip->open($temp_zip, ZipArchive::CREATE);

    $dir = new RecursiveDirectoryIterator($temp_dir);
    $files = new RecursiveIteratorIterator($dir);
    foreach ($files as $name => $object) {
      $path = preg_replace("#^{$temp_dir}/#", "", $name);
      $zip->addFile($name, $path);
    }
    $zip->close();

    $profile = base64_encode(file_get_contents($temp_zip));
    return $profile;
  }

  private function installExtension($extension, $profile_dir) {
    $temp_dir = $this->createTempDirectory();

    $this->extractTo($extension, $temp_dir);

    $install_rdf_path = $temp_dir.'/install.rdf';
    $xml = simplexml_load_string(file_get_contents($install_rdf_path));
    $ext_dir = $profile_dir.'/extensions/'.((string)($xml->Description->id));
    mkdir($ext_dir, 0777, true);

    $this->extractTo($extension, $ext_dir);
    return $ext_dir;
  }

  private function createTempDirectory($prefix = '') {
    $temp_dir = tempnam('', $prefix);
    if (file_exists($temp_dir)) {
      unlink($temp_dir);
      mkdir($temp_dir);
      if (!is_dir($temp_dir)) {
        throw new WebDriverException('Cannot create firefox profile.');
      }
    }
    return $temp_dir;
  }

  private function extractTo($xpi, $target_dir) {
    $zip = new ZipArchive();
    if ($zip->open($xpi)) {
      $zip->extractTo($target_dir);
      $zip->close();
    } else {
      throw new Exception("Failed to open the firefox extension. '$xpi'");
    }
  }
}
