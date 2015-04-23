<?php

namespace PeskyORM\Lib;

require_once 'File.php';

class ImageUtils {

    static public $contentTypeToExtension = array(
//        "image/gif" => "gif",
        "image/jpeg" => "jpg",
        "image/pjpeg" => "jpg",
        "image/x-png" => "png",
        "image/jpg" => "jpg",
        "image/png" => "png",
//        "image/bmp" => "bmp",
    );

    static public $defaultResizeSettings = array(
        'w' => null,
        'h' => null,
        'crop' => false,
        'center' => true,
        'enlarge' => false,
        'convert' => false,
        'jpeg_quality' => 90,
        'png_quality' => 9
    );

    static public $originalFileNameSuffix = '-original';
    static public $originalFileResizeSettings = array(
        'w' => 1920,
        'h' => 1200,
        'crop' => false,
        'center' => false,
        'enlarge' => false,
    );

    static public function getOriginalFileName($fileName) {
        return $fileName . self::$originalFileNameSuffix;
    }

    /**
     * Save uploaded image ($fileInfo) and create several resized versions ($resizeProfiles)
     * @param array $fileInfo - data from $_FILES
     * @param string $imagesPath - folder to save images in
     * @param string $fileName - base file name (used as prefix for resized file name)
     * @param array $resizeProfiles - set of resize settings
     * @return bool - false: file's content type not supported | true: saved & resized
     */
    static public function resize($fileInfo, $imagesPath, $fileName, $resizeProfiles) {
        $contentType = self::getContentTypeForUploadedFile($fileInfo);
        if (!self::isContentTypeSupported($contentType) || empty($imagesPath) || empty($fileName)) {
            return false;
        }
        if (is_dir($imagesPath)) {
            self::deleteExistingFiles($imagesPath, self::getFileNamesRegexp($fileName));
        } else {
            Folder::add($imagesPath, 0777);
        }
        // save original file (limited by w and h)
        $originalFileResizeInfo = self::$originalFileResizeSettings;
        $originalFileResizeInfo['type'] = $contentType;
        $originalFilePath = self::applyResize(
            $fileInfo['tmp_name'],
            $imagesPath . self::getOriginalFileName($fileName),
            $originalFileResizeInfo
        );
        @File::remove($fileInfo['tmp_name']); //< remove temp file and use original file
        // save other file versions
        foreach ($resizeProfiles as $resizeSettings) {
            $resizeSettings = array_merge(self::$defaultResizeSettings, $resizeSettings);
            $resizeSettings['type'] = $contentType;
            $newFileName = $fileName . self::getFileSuffix($resizeSettings);
            self::applyResize($originalFilePath, $imagesPath . $newFileName, $resizeSettings);
        }
        return true;
    }

    /**
     * Delete all image files that match $fileNameRegexp
     * @param string $imagesPath,
     * @param string $fileNameRegexp
     */
    static public function deleteExistingFiles($imagesPath, $fileNameRegexp) {
        if (is_dir($imagesPath)) {
            $files = scandir($imagesPath);
            foreach ($files as $fileName) {
                if (preg_match($fileNameRegexp, $fileName)) {
                    @File::remove(rtrim($imagesPath, '/\\') . DIRECTORY_SEPARATOR . $fileName);
                }
            }
        }
    }

    /**
     * Rotate image
     * @param string $filePath
     * @param string $fileType - jpeg | gif | png
     * @param string|null $newFilePath - null: owerwrite $filePath
     * @param int $degrees - clockwise
     * @return bool
     */
    static public function rotate($filePath, $fileType, $degrees, $newFilePath = null) {
        if (empty($degrees)) {
            return true;
        } else if (intval($degrees) && !empty($filePath) && file_exists($filePath) && !is_dir($filePath)) {
            $srcImage = call_user_func('imagecreatefrom' . $fileType, $filePath);
            $resultImage = imagerotate($srcImage, $degrees * -1, 0);
            imagedestroy($srcImage);
            if ($resultImage) {
                if (empty($newFilePath)) {
                    $newFilePath = $filePath;
                }
                call_user_func("image" . $fileType, $resultImage, $newFilePath, $fileType == 'png' ? 8 : 70);
                File::load($newFilePath)->chmod(0666);
                imagedestroy($resultImage);
            }

            return !!$resultImage;
        }
        return false;
    }

    /**
     * Collect resize information, resize source image and save results
     * Note: image will be copied without resizing if new dimensions are higher then original and enlarging is not allowed.
     * @param string $srcFilePath - absolute path to source image file
     * @param string $newFilePath - absolute path to resized image file (without extension)
     * @param array $resizeSettings - resizing settings
     * @return string - resized file path
     */
    static protected function applyResize($srcFilePath, $newFilePath, $resizeSettings) {
        $resizeSettings = array_merge(self::$defaultResizeSettings, $resizeSettings);
        $resizeInfo = self::getOptimizedImageSizes(
            $srcFilePath,
            $resizeSettings['w'],
            $resizeSettings['h'],
            $resizeSettings['crop'],
            $resizeSettings['enlarge'],
            $resizeSettings['center']
        );
        if (empty($resizeSettings['convert'])) {
            $contentType = $resizeSettings['type'];
        } else {
            $resizeInfo['convert'] = $resizeSettings['convert'];
            $contentType = 'image/' .  $resizeSettings['convert'];
        }
        $resizeInfo['jpeg_quality'] = $resizeSettings['jpeg_quality'];
        $resizeInfo['png_quality'] = $resizeSettings['png_quality'];
        $newFilePath .= '.' . self::getExtensionByContentType($contentType);
        if (!$resizeInfo['resize']) {
            // resizing is not allowed or not required
            if ($srcFilePath != $newFilePath) {
                File::load($srcFilePath)->copy($newFilePath, true, 0666);
            }
        } else {
            // check if resized file is up to date and do not need to be resized
            $skipResize = false;
            if (file_exists($newFilePath)) {
                // check dimensions
                list($exWidth, $exHeight) = getimagesize($newFilePath);
                $skipResize = ($exWidth == $resizeInfo['new_width'] && $exHeight == $resizeInfo['new_height']);
                // check if up to date
                if ($skipResize && (@filemtime($newFilePath) < @filemtime($srcFilePath))) {
                    $skipResize = false;
                }
            }
            if (!$skipResize) {
                self::resizeImage($srcFilePath, $newFilePath, $resizeInfo);
            }
        }
        return $newFilePath;
    }

    /**
     * Calculate image dimensions for resizing
     * @param string $filePath - absolute path to source image file
     * @param int $fitWidth - required width
     * @param int $fitHeight - required height
     * @param bool $allowCrop - true: new width [and] height will be equal to required but image may be cropped
     *                          false: new width [or] height may be lower then required but image won't be cropped
     * @param bool $allowEnlarge - true: allows to enlarge small image to fit required width and height
     * @param bool $allowCenter - true: allows to center cropped image
     * @return array = array (
     *      'fit_width' => int, 'fit_reight' => int,            //< required dimensions
     *      'original_width' => int, 'original_height' => int,  //< original dimensions
     *      'type' => string,           //< file type ("gif", "jpeg", "png", "swf", "psd", "wbmp")
     *      'resize' => bool,           //< true: resize required | false: resize not required or not alllowed
     *      'aspect_ratio' => float,    //< resizing ratio
     *      'lossless_width' => int, 'lossless_height' => int,  //< new lossless dimensions (without cropping)
     *      'new_width' => int, 'new_height' => int,  //< new dimensions (with cropping if enabled),
     *      'x' => int, 'y' => int,     //< resized image positioning
     *  )
     */
    static protected function getOptimizedImageSizes($filePath, $fitWidth, $fitHeight, $allowCrop = false,
    $allowEnlarge = false, $allowCenter = true) {
        $fitWidth = intval($fitWidth);
        $fitHeight = intval($fitHeight);
        $types = array(1 => "gif", "jpeg", "png", "swf", "psd", "wbmp"); // used to determine image type
        $resizeInfo = array(
            'aspect_ratio' => 1,
            'resize' => false,
            'fit_width' => $fitWidth,
            'fit_reight' => $fitHeight,
            'x' => 0,
            'y' => 0
        );
        list($resizeInfo['original_width'], $resizeInfo['original_height'], $resizeInfo['type']) = getimagesize($filePath);
        $resizeInfo['type'] = $types[$resizeInfo['type']];
        $resizeInfo['lossless_width'] = $resizeInfo['new_width'] = $resizeInfo['original_width'];
        $resizeInfo['lossless_height'] = $resizeInfo['new_height'] = $resizeInfo['original_height'];
        // exit if no resize required (empty w and h or h and h same as original image)
        if (
            (empty($fitWidth) && empty($fitHeight))
            || ($resizeInfo['original_width'] == $fitWidth && $resizeInfo['original_height'] == $fitHeight)
        ) {
            return $resizeInfo;
        }
        // exit if image is too small and enlarge is not allowed
        if (
            !$allowEnlarge &&
            (
                ($resizeInfo['original_width'] < $fitWidth && $resizeInfo['original_height'] < $fitHeight)
                || ($resizeInfo['original_width'] < $fitWidth && empty($fitHeight))
                || ($resizeInfo['original_height'] < $fitHeight && empty($fitWidth))
            )
        ) {
            return $resizeInfo;
        }
        $resizeInfo['resize'] = true;
        // count dimension changes
        $aspectByWidth = $fitWidth / $resizeInfo['original_width'];
        $aspectByHeight = $fitHeight / $resizeInfo['original_height'];
        $testHeight = round($aspectByWidth * $resizeInfo['original_height']);
        $testWidth = round($aspectByHeight * $resizeInfo['original_width']);
        if (empty($fitWidth)) {
            // situation when width is not limited
            $aspectRatio = $aspectByHeight;
            $fitWidth = round($resizeInfo['original_width'] * $aspectRatio);
            $resizeInfo['fit_width_upd'] = $fitWidth;
        } else if (empty($fitHeight)) {
            // situation when height is not limited
            $aspectRatio = $aspectByWidth;
            $fitHeight = round($resizeInfo['original_height'] * $aspectRatio);
            $resizeInfo['fit_height_upd'] = $fitHeight;
        } else if ($allowCrop) {
            $aspectRatio = ($testHeight < $fitHeight || $testWidth == $fitWidth) ? $aspectByHeight : $aspectByWidth;
        } else {
            $aspectRatio = ($testHeight < $fitHeight || $testWidth > $fitWidth) ? $aspectByWidth : $aspectByHeight;
        }
        // count new dimensions
        $resizeInfo['lossless_width'] = round($resizeInfo['original_width'] * $aspectRatio, 0);
        $resizeInfo['new_width'] = $allowCrop ? $fitWidth : $resizeInfo['lossless_width'];
        $resizeInfo['lossless_height'] = round($resizeInfo['original_height'] * $aspectRatio, 0);
        $resizeInfo['new_height'] = $allowCrop ? $fitHeight : $resizeInfo['lossless_height'];
        $resizeInfo['aspect_ratio'] = $aspectRatio;
        if ($allowCenter) {
            $resizeInfo['x'] = round(($resizeInfo['lossless_width'] - $resizeInfo['new_width']) / 2) * -1;
            $resizeInfo['y'] = round(($resizeInfo['lossless_height'] - $resizeInfo['new_height']) / 2) * -1;
        }
        return $resizeInfo;
    }

    /**
     * Resize source image and save results
     * @param string $srcFilePath - absolute path to source image file
     * @param string $resizedFilePath - absolute path to resized image file
     * @param array $resizeInfo - information received from $this->getOptimizedImageSizes()
     */
    static public function resizeImage($srcFilePath, $resizedFilePath, $resizeInfo) {
        $srcImage = call_user_func('imagecreatefrom' . $resizeInfo['type'], $srcFilePath);
        if (function_exists("imagecreatetruecolor")) {
            $resizedImage = imagecreatetruecolor($resizeInfo['new_width'], $resizeInfo['new_height']);
            if ($resizedImage) {
                if ($resizeInfo['type'] == 'png') {
                    // Turn off alpha blending and set alpha flag
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);
                    $transparency = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                    imagefilledrectangle($resizedImage, 0, 0, $resizeInfo['new_width'], $resizeInfo['new_height'], $transparency);
                }
                imagecopyresampled(
                    $resizedImage, $srcImage,
                    $resizeInfo['x'], $resizeInfo['y'], 0, 0,
                    $resizeInfo['lossless_width'], $resizeInfo['lossless_height'],
                    $resizeInfo['original_width'], $resizeInfo['original_height']
                );
            }
        }
        if (empty($resizedImage)) {
            $resizedImage = imagecreate($resizeInfo['new_width'], $resizeInfo['new_height']);
            imagecopyresized(
                $resizedImage, $srcImage,
                $resizeInfo['x'], $resizeInfo['y'], 0, 0,
                $resizeInfo['lossless_width'], $resizeInfo['lossless_height'],
                $resizeInfo['original_width'], $resizeInfo['original_height']
            );
        }
        $targetFileType = empty($resizeInfo['convert']) ? $resizeInfo['type'] : $resizeInfo['convert'];
        $quality = $targetFileType == 'png' ? $resizeInfo['png_quality'] : $resizeInfo['jpeg_quality'];
        call_user_func('image' . $targetFileType, $resizedImage, $resizedFilePath, $quality);
        File::load($resizedFilePath)->chmod(0666);
        imagedestroy($srcImage);
        imagedestroy($resizedImage);
    }

    /**
     * Test if uploaded file is image
     * @param array $fileInfo - data from $_FILES
     * @return bool
     */
    static public function isImage($fileInfo) {
        return !empty($fileInfo) && !empty($fileInfo['size']) && self::isContentTypeSupported($fileInfo['type']);
    }

    /**
     * Find content type of uploaded file
     * @param array $fileInfo - uploaded file info provided by CakePHP. Should contain 'type', 'name' and 'tmp_name' keys
     * @return string
     */
    static public function getContentTypeForUploadedFile($fileInfo) {
        if (
            !self::isContentTypeSupported($fileInfo['type'])
            && preg_match('%\.(' . implode('|' ,self::$contentTypeToExtension) . ')$%is', $fileInfo['name'])
        ) {
            // fix incorrect mime type for files with allowed extensions
            $fileInfo['type'] = mime_content_type($fileInfo['tmp_name']);
        }
        return $fileInfo['type'];
    }

    /**
     * Verify if content type is supported
     * @param string $contentType
     * @return bool
     */
    static public function isContentTypeSupported($contentType) {
        return in_array($contentType, array_keys(self::$contentTypeToExtension));
    }

    /**
     * Resolve file's content type to its extension
     * @param string $contentType
     * @return bool
     */
    static public function getExtensionByContentType($contentType) {
        if (isset(self::$contentTypeToExtension[$contentType])) {
            return self::$contentTypeToExtension[$contentType];
        } else {
            return false;
        }
    }

    /**
     * Resolve file's content type to its extension
     * @param string $ext
     * @return bool
     */
    static public function getContentTypeByExtension($ext) {
        $extToContentType = array_flip(self::$contentTypeToExtension);
        if (isset($extToContentType[$ext])) {
            return $extToContentType[$ext];
        } else {
            return false;
        }
    }

    /**
     * Restore single image version by file name
     * @param string $fileNameToRestore
     * @param string $baseFileName
     * @param string $imagesPath
     * @param array $resizeProfiles
     * @return bool|string - false: fail | string: created file path
     */
    static public function restoreVersion($fileNameToRestore, $baseFileName, $imagesPath, $resizeProfiles) {
        $originalFileName = self::getOriginalFileName($baseFileName);
        $ext = self::findFileExtension($imagesPath, $originalFileName);
        if ($ext) {
            $originalFileName .= '.' . $ext;
            foreach ($resizeProfiles as $profile => $resizeSettings) {
                $versionFileName = $baseFileName . self::getFileSuffix($resizeSettings);
                if ($fileNameToRestore == $versionFileName) {
                    $extToContentType = array_flip(self::$contentTypeToExtension);
                    $resizeSettings['type'] = $extToContentType[$ext];
                    $ext = self::findFileExtension($imagesPath, $versionFileName);
                    if (!$ext) {
                        return self::applyResize($imagesPath . $originalFileName, $imagesPath . $versionFileName, $resizeSettings);
                    } else {
                        return $imagesPath . $versionFileName . '.' . $ext;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Collect urls to all versions of images
     * @param string $imagesPath - path to images
     * @param string $imagesBaseUrl - base url to images
     * @param string $fileName - base files name without suffix and extension
     * @param array $resizeProfiles
     * @return array
     */
    static public function getVersionsUrls($imagesPath, $imagesBaseUrl, $fileName, $resizeProfiles) {
        $originalFileName = self::getOriginalFileName($fileName);
        $ext = self::findFileExtension($imagesPath, $originalFileName);
        $result = array('original' => $imagesBaseUrl . $originalFileName);
        if ($ext) {
            $result['original'] .= '.' . $ext;
        }
        foreach ($resizeProfiles as $profile => $resizeSettings) {
            $profileExt = !empty($resizeSettings['convert'])
                ? self::getExtensionByContentType('image/' . $resizeSettings['convert'])
                : $ext;
            $result[$profile] = $imagesBaseUrl . $fileName . self::getFileSuffix($resizeSettings);
            if ($profileExt) {
                $result[$profile] .= '.' . $profileExt;
            }
        }
        return $result;
    }

    /**
     * Collect fs paths to all versions of images
     * @param string $imagesPath - path to images
     * @param string $fileName - base files name without suffix and extension
     * @param array $resizeProfiles
     * @return array
     */
    static public function getVersionsPaths($imagesPath, $fileName, $resizeProfiles) {
        $originalFileName = self::getOriginalFileName($fileName);
        $ext = self::findFileExtension($imagesPath, $originalFileName);
        $result = array('original' => '');
        if ($ext) {
            $result['original'] = $imagesPath . $originalFileName . '.' . $ext;
        }
        foreach ($resizeProfiles as $profile => $resizeSettings) {
            $profileExt = !empty($resizeSettings['convert'])
                ? self::getExtensionByContentType('image/' . $resizeSettings['convert'])
                : $ext;
            $result[$profile] = $profileExt
                ? $imagesPath . $fileName . self::getFileSuffix($resizeSettings) . '.' . $profileExt
                : '';
        }
        return $result;
    }

    /**
     * Find file extesion among allowed file extensions
     * @param string $imagesPath
     * @param string $originalFileName
     * @return bool|string
     */
    static protected function findFileExtension($imagesPath, $originalFileName) {
        $ext = false;
        foreach (array_unique(self::$contentTypeToExtension) as $testExt) {
            if (File::exist(rtrim($imagesPath, '\\/') . DIRECTORY_SEPARATOR . $originalFileName . '.' . $testExt)) {
                $ext = $testExt;
                break;
            }
        }
        return $ext;
    }

    /**
     * Create image file name suffix with width and height (ex: '_100x100')
     * @param array $resizeSettings
     * @return string
     */
    static protected function getFileSuffix($resizeSettings) {
        return '-' . intval($resizeSettings['w']) . 'x' . intval($resizeSettings['h']);
    }

    /**
     * Get regexp that will match required files
     * @param $fileName
     * @return string
     */
    static public function getFileNamesRegexp($fileName) {
        return "%^($fileName-(\d+)x(\d+)|" . self::getOriginalFileName($fileName) . ')%is';
    }

}
