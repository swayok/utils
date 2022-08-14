<?php

namespace Swayok\Utils;

use Swayok\Utils\Exception\ImageUtilsException;

abstract class ImageUtils
{
    
    static public $contentTypeToExtension = [
//        "image/gif" => "gif",
        "image/jpeg" => "jpg",
        "image/pjpeg" => "jpg",
        "image/x-png" => "png",
        "image/jpg" => "jpg",
        "image/png" => "png",
//        "image/bmp" => "bmp",
    ];
    
    public static function getDefaultOriginalFileVersionConfig(): ImageVersionConfig
    {
        return ImageVersionConfig::create()
            ->setWidth(1920)
            ->setHeight(1200)
            ->disallowCrop()
            ->disallowEnlarge()
            ->disallowCentering();
    }
    
    /**
     * Save uploaded image ($fileInfo) and create several resized versions ($resizeProfiles)
     * @param array $uploadedFileInfo - data from $_FILES
     * @param string $imagesPath - folder to save images in
     * @param string $baseFileNameWithoutExtension - base file name (used as prefix for resized file name)
     * @param ImageVersionConfig[] $imageVersionsConfigs - set of resize settings
     * @return array - list of resized files names with versions names as keys
     * @throws ImageUtilsException
     */
    public static function resize(
        array $uploadedFileInfo,
        string $imagesPath,
        string $baseFileNameWithoutExtension,
        array $imageVersionsConfigs
    ): array {
        $contentType = self::getContentTypeForUploadedFile($uploadedFileInfo);
        if (empty($imagesPath) || empty($baseFileNameWithoutExtension) || !self::isContentTypeSupported($contentType)) {
            throw new ImageUtilsException('Uploaded image type is not supported', 403);
        }
        if (is_dir($imagesPath)) {
            self::deleteExistingFiles($imagesPath, self::getFileNamesRegexp($baseFileNameWithoutExtension));
        } else {
            Folder::add($imagesPath, 0777);
        }
        // save original file (limited by w and h)
        if (empty($imageVersionsConfigs[ImageVersionConfig::SOURCE_VERSION_NAME])) {
            $originalFileResizeConfig = self::getDefaultOriginalFileVersionConfig();
        } else {
            $originalFileResizeConfig = $imageVersionsConfigs[ImageVersionConfig::SOURCE_VERSION_NAME];
        }
        $originalFileName = $baseFileNameWithoutExtension . self::applyResize(
                $uploadedFileInfo['tmp_name'],
                $imagesPath . $baseFileNameWithoutExtension,
                $originalFileResizeConfig,
                $contentType
            );
        @File::remove($uploadedFileInfo['tmp_name']); //< remove temp file and use original file
        $filesNames = [
            ImageVersionConfig::SOURCE_VERSION_NAME => $originalFileName,
        ];
        // save other file versions
        foreach ($imageVersionsConfigs as $versionName => $resizeSettings) {
            if ($versionName !== ImageVersionConfig::SOURCE_VERSION_NAME) {
                $newFileName = $baseFileNameWithoutExtension . $resizeSettings->getFileNameSuffix($versionName);
                $ext = self::applyResize(
                    $imagesPath . $originalFileName,
                    $imagesPath . $newFileName,
                    $resizeSettings,
                    $contentType
                );
                $filesNames[$versionName] = $newFileName . $ext;
            }
        }
        return $filesNames;
    }
    
    /**
     * Delete all image files that match $fileNameRegexp
     */
    public static function deleteExistingFiles(string $imagesPath, string $fileNameRegexp): void
    {
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
     * Collect resize information, resize source image and save results
     * Note: image will be copied without resizing if new dimensions are higher then original and enlarging is not allowed.
     * @param string $srcFilePath - absolute path to source image file
     * @param string $newFilePath - absolute path to resized image file (without extension)
     * @param ImageVersionConfig $imageVersionConfig - resizing settings
     * @param string $contentType - jpeg|png|gif, etc
     * @return string - resized file extension
     */
    protected static function applyResize(
        string $srcFilePath,
        string $newFilePath,
        ImageVersionConfig $imageVersionConfig,
        string $contentType
    ): string {
        $resizeInfo = self::getOptimizedImageSizes($srcFilePath, $imageVersionConfig);
        if ($imageVersionConfig->isContentTypeConvertRequired()) {
            $contentType = 'image/' . $imageVersionConfig->getContentTypeToConvertTo();
        }
        $ext = '.' . self::getExtensionByContentType($contentType);
        $newFilePath .= $ext;
        if (!$resizeInfo['resize']) {
            // resizing is not allowed or not required
            if ($srcFilePath !== $newFilePath) {
                File::load($srcFilePath)
                    ->copy($newFilePath, true, 0666);
            }
        } else {
            // check if resized file is up to date and do not need to be resized
            $skipResize = false;
            if (file_exists($newFilePath)) {
                // check dimensions
                [$exWidth, $exHeight] = getimagesize($newFilePath);
                $skipResize = ($exWidth === $resizeInfo['new_width'] && $exHeight === $resizeInfo['new_height']);
                // check if up to date
                if ($skipResize && (@filemtime($newFilePath) < @filemtime($srcFilePath))) {
                    $skipResize = false;
                }
            }
            if (!$skipResize) {
                self::resizeImage($srcFilePath, $newFilePath, $resizeInfo, $imageVersionConfig);
            }
        }
        return $ext;
    }
    
    /**
     * Calculate image dimensions for resizing
     * @param string $filePath - absolute path to source image file
     * @param ImageVersionConfig $imageVersionConfig
     * @return array = array (
     * 'fit_width' => int, 'fit_height' => int,            //< required dimensions
     * 'original_width' => int, 'original_height' => int,  //< original dimensions
     * 'type' => string,           //< file type ("gif", "jpeg", "png", "swf", "psd", "wbmp")
     * 'resize' => bool,           //< true: resize required | false: resize not required or not alllowed
     * 'aspect_ratio' => float,    //< resizing ratio
     * 'lossless_width' => int, 'lossless_height' => int,  //< new lossless dimensions (without cropping)
     * 'new_width' => int, 'new_height' => int,  //< new dimensions (with cropping if enabled),
     * 'x' => int, 'y' => int,     //< resized image positioning
     * )
     */
    protected static function getOptimizedImageSizes(string $filePath, ImageVersionConfig $imageVersionConfig): array
    {
        $types = [1 => "gif", "jpeg", "png", "swf", "psd", "wbmp"]; // used to determine image type
        $resizeInfo = [
            'aspect_ratio' => 1,
            'resize' => false,
            'fit_width' => $imageVersionConfig->getWidth(),
            'fit_height' => $imageVersionConfig->getHeight(),
            'x' => 0,
            'y' => 0,
            'type' => null,
            'convert' => $imageVersionConfig->isContentTypeConvertRequired()
                ? $imageVersionConfig->getContentTypeToConvertTo()
                : false,
        ];
        [$resizeInfo['original_width'], $resizeInfo['original_height'], $resizeInfo['type']] = getimagesize($filePath);
        $resizeInfo['type'] = $types[$resizeInfo['type']];
        $resizeInfo['lossless_width'] = $resizeInfo['new_width'] = $resizeInfo['original_width'];
        $resizeInfo['lossless_height'] = $resizeInfo['new_height'] = $resizeInfo['original_height'];
        // exit if no resize required (empty w and h or h and h same as original image)
        if (
            (empty($resizeInfo['fit_width']) && empty($resizeInfo['fit_height']))
            || (
                $resizeInfo['original_width'] === $resizeInfo['fit_width']
                && $resizeInfo['original_height'] === $resizeInfo['fit_height']
            )
        ) {
            return $resizeInfo;
        }
        // exit if image is too small and enlarge is not allowed
        /** @noinspection NotOptimalIfConditionsInspection */
        if (
            !$imageVersionConfig->isEnlargeAllowed()
            && (
                (
                    $resizeInfo['original_width'] < $resizeInfo['fit_width']
                    && $resizeInfo['original_height'] < $resizeInfo['fit_height']
                )
                || (
                    $resizeInfo['original_width'] < $resizeInfo['fit_width']
                    && empty($resizeInfo['fit_height'])
                )
                || (
                    $resizeInfo['original_height'] < $resizeInfo['fit_height']
                    && empty($resizeInfo['fit_width'])
                )
            )
        ) {
            return $resizeInfo;
        }
        $resizeInfo['resize'] = true;
        // count dimension changes
        $aspectByWidth = $resizeInfo['fit_width'] / $resizeInfo['original_width'];
        $aspectByHeight = $resizeInfo['fit_height'] / $resizeInfo['original_height'];
        $testHeight = round($aspectByWidth * $resizeInfo['original_height']);
        $testWidth = round($aspectByHeight * $resizeInfo['original_width']);
        if (empty($resizeInfo['fit_width'])) {
            // situation when width is not limited
            $aspectRatio = $aspectByHeight;
            $resizeInfo['fit_width'] = round($resizeInfo['original_width'] * $aspectRatio);
            $resizeInfo['fit_width_upd'] = $resizeInfo['fit_width'];
        } elseif (empty($resizeInfo['fit_height'])) {
            // situation when height is not limited
            $aspectRatio = $aspectByWidth;
            $resizeInfo['fit_height'] = round($resizeInfo['original_height'] * $aspectRatio);
            $resizeInfo['fit_height_upd'] = $resizeInfo['fit_height'];
        } elseif ($imageVersionConfig->isCropAllowed()) {
            $aspectRatio = ($testHeight < $resizeInfo['fit_height'] || $testWidth === $resizeInfo['fit_width']) ? $aspectByHeight : $aspectByWidth;
        } else {
            $aspectRatio = ($testHeight < $resizeInfo['fit_height'] || $testWidth > $resizeInfo['fit_width']) ? $aspectByWidth : $aspectByHeight;
        }
        // count new dimensions
        $resizeInfo['lossless_width'] = round($resizeInfo['original_width'] * $aspectRatio);
        $resizeInfo['new_width'] = $imageVersionConfig->isCropAllowed()
            ? $resizeInfo['fit_width']
            : $resizeInfo['lossless_width'];
        $resizeInfo['lossless_height'] = round($resizeInfo['original_height'] * $aspectRatio);
        $resizeInfo['new_height'] = $imageVersionConfig->isCropAllowed()
            ? $resizeInfo['fit_height']
            : $resizeInfo['lossless_height'];
        $resizeInfo['aspect_ratio'] = $aspectRatio;
        if ($imageVersionConfig->isCenteringAllowed()) {
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
     * @param ImageVersionConfig $imageVersionConfig
     */
    public static function resizeImage(
        string $srcFilePath,
        string $resizedFilePath,
        array $resizeInfo,
        ImageVersionConfig $imageVersionConfig
    ): void {
        $srcImage = call_user_func('imagecreatefrom' . $resizeInfo['type'], $srcFilePath);
        if (function_exists("imagecreatetruecolor")) {
            $resizedImage = imagecreatetruecolor($resizeInfo['new_width'], $resizeInfo['new_height']);
            if ($resizedImage) {
                if ($resizeInfo['type'] === 'png') {
                    // Turn off alpha blending and set alpha flag
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);
                    $transparency = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                    imagefilledrectangle($resizedImage, 0, 0, $resizeInfo['new_width'], $resizeInfo['new_height'], $transparency);
                }
                imagecopyresampled(
                    $resizedImage,
                    $srcImage,
                    $resizeInfo['x'],
                    $resizeInfo['y'],
                    0,
                    0,
                    $resizeInfo['lossless_width'],
                    $resizeInfo['lossless_height'],
                    $resizeInfo['original_width'],
                    $resizeInfo['original_height']
                );
            }
        }
        if (empty($resizedImage)) {
            $resizedImage = imagecreate($resizeInfo['new_width'], $resizeInfo['new_height']);
            imagecopyresized(
                $resizedImage,
                $srcImage,
                $resizeInfo['x'],
                $resizeInfo['y'],
                0,
                0,
                $resizeInfo['lossless_width'],
                $resizeInfo['lossless_height'],
                $resizeInfo['original_width'],
                $resizeInfo['original_height']
            );
        }
        $targetFileType = empty($resizeInfo['convert']) ? $resizeInfo['type'] : $resizeInfo['convert'];
        $quality = $targetFileType === 'png' ? $imageVersionConfig->getPngQuality() : $imageVersionConfig->getJpegQuality();
        call_user_func('image' . $targetFileType, $resizedImage, $resizedFilePath, $quality);
        File::load($resizedFilePath)
            ->chmod(0666);
        imagedestroy($srcImage);
        imagedestroy($resizedImage);
    }
    
    /**
     * Rotate image
     * @param string $filePath
     * @param string $fileType - jpeg | gif | png
     * @param string|null $newFilePath - null: owerwrite $filePath
     * @param int $degrees - clockwise
     * @return bool
     */
    public static function rotate(string $filePath, string $fileType, int $degrees, ?string $newFilePath = null): bool
    {
        $degrees %= 360;
        if ($degrees === 0) {
            return true;
        } elseif (!empty($filePath) && file_exists($filePath) && !is_dir($filePath)) {
            $srcImage = call_user_func('imagecreatefrom' . $fileType, $filePath);
            $resultImage = imagerotate($srcImage, $degrees * -1, 0);
            imagedestroy($srcImage);
            if ($resultImage) {
                if (empty($newFilePath)) {
                    $newFilePath = $filePath;
                }
                call_user_func("image" . $fileType, $resultImage, $newFilePath, $fileType === 'png' ? 8 : 70);
                File::load($newFilePath)
                    ->chmod(0666);
                imagedestroy($resultImage);
            }
            
            return (bool)$resultImage;
        }
        return false;
    }
    
    /**
     * Test if uploaded file is image
     * @param array $fileInfo - data from $_FILES
     * @return bool
     */
    public static function isImage(array $fileInfo): bool
    {
        return !empty($fileInfo) && !empty($fileInfo['size']) && self::isContentTypeSupported($fileInfo['type']);
    }
    
    /**
     * Find content type of uploaded file
     * @param array $fileInfo - uploaded file info provided by CakePHP. Should contain 'type', 'name' and 'tmp_name' keys
     * @return string
     */
    public static function getContentTypeForUploadedFile(array $fileInfo): string
    {
        if (
            !self::isContentTypeSupported($fileInfo['type'])
            && preg_match('%\.(' . implode('|', self::$contentTypeToExtension) . ')$%is', $fileInfo['name'])
        ) {
            // fix incorrect mime type for files with allowed extensions
            $fileInfo['type'] = mime_content_type($fileInfo['tmp_name']);
        }
        return $fileInfo['type'];
    }
    
    /**
     * Verify if content type is supported
     */
    public static function isContentTypeSupported(string $contentType): bool
    {
        return array_key_exists($contentType, self::$contentTypeToExtension);
    }
    
    /**
     * Resolve file's content type to its extension
     */
    public static function getExtensionByContentType(string $contentType): ?string
    {
        return self::$contentTypeToExtension[$contentType] ?? null;
    }
    
    /**
     * Resolve file's content type to its extension
     */
    public static function getContentTypeByExtension(string $ext): ?string
    {
        $extToContentType = array_flip(self::$contentTypeToExtension);
        return $extToContentType[$ext] ?? null;
    }
    
    /**
     * Restore single image version by file name
     * @param string $fileNameToRestore
     * @param string $baseFileName
     * @param string $imagesPath
     * @param ImageVersionConfig[] $imageVersionsConfigs
     * @return null|string - null: fail | string: created file path
     */
    public static function restoreVersion(
        string $fileNameToRestore,
        string $baseFileName,
        string $imagesPath,
        array $imageVersionsConfigs
    ): ?string {
        $originalFileName = $baseFileName;
        $ext = self::findFileExtension($imagesPath, $originalFileName);
        if ($ext) {
            $originalFileName .= '.' . $ext;
            foreach ($imageVersionsConfigs as $versionName => $imageVersionConfig) {
                $versionFileName = $baseFileName . $imageVersionConfig->getFileNameSuffix($versionName);
                if ($fileNameToRestore === $versionFileName) {
                    $extToContentType = array_flip(self::$contentTypeToExtension);
                    $contentType = $extToContentType[$ext];
                    $ext = self::findFileExtension($imagesPath, $versionFileName);
                    if (!$ext) {
                        $ext = self::applyResize(
                            $imagesPath . $originalFileName,
                            $imagesPath . $versionFileName,
                            $imageVersionConfig,
                            $contentType
                        );
                    }
                    return $imagesPath . $versionFileName . '.' . ltrim($ext, '.');
                }
            }
        }
        return null;
    }
    
    /**
     * @param string $versionName
     * @param ImageVersionConfig $versionConfig
     * @param string $baseFileName
     * @param string $imagesPath
     * @param string|null $fileExtension
     * @return string|null - null - source file not exists
     */
    public static function restoreVersionForConfig(
        string $versionName,
        ImageVersionConfig $versionConfig,
        string $baseFileName,
        string $imagesPath,
        ?string $fileExtension = null
    ): ?string {
        $extToContentType = array_flip(self::$contentTypeToExtension);
        if (!$fileExtension) {
            $contentType = $versionConfig->getContentTypeToConvertTo();
        } else {
            $contentType = $extToContentType[$fileExtension];
        }
        $versionFileName = $baseFileName . $versionConfig->getFileNameSuffix($versionName);
        $srcFile = $imagesPath . $baseFileName . '.' . self::findFileExtension($imagesPath, $baseFileName);
        if (!File::exist($srcFile)) {
            return null;
        }
        $ext = self::applyResize(
            $imagesPath . $baseFileName . '.' . self::findFileExtension($imagesPath, $baseFileName),
            $imagesPath . $versionFileName,
            $versionConfig,
            $contentType
        );
        return $imagesPath . $versionFileName . $ext;
    }
    
    /**
     * Collect urls to all versions of images
     * @param string $imagesPath - path to images
     * @param string $imagesBaseUrl - base url to images
     * @param string $fileName - base files name without suffix and extension
     * @param ImageVersionConfig[] $imageVersionsConfigs
     * @return array
     */
    public static function getVersionsUrls(
        string $imagesPath,
        string $imagesBaseUrl,
        string $fileName,
        array $imageVersionsConfigs
    ): array {
        $originalFileName = $fileName;
        $ext = self::findFileExtension($imagesPath, $originalFileName);
        $result = [ImageVersionConfig::SOURCE_VERSION_NAME => $imagesBaseUrl . $originalFileName];
        if ($ext) {
            $result[ImageVersionConfig::SOURCE_VERSION_NAME] .= '.' . $ext;
        }
        foreach ($imageVersionsConfigs as $versionName => $imageVersionConfig) {
            if ($versionName !== ImageVersionConfig::SOURCE_VERSION_NAME) {
                $profileExt = $imageVersionConfig->isContentTypeConvertRequired()
                    ? self::getExtensionByContentType('image/' . $imageVersionConfig->getContentTypeToConvertTo())
                    : $ext;
                $result[$versionName] = $imagesBaseUrl . $fileName . $imageVersionConfig->getFileNameSuffix($versionName);
                if ($profileExt) {
                    $result[$versionName] .= '.' . $profileExt;
                }
            }
        }
        return $result;
    }
    
    /**
     * Collect fs paths to all versions of images
     * @param string $imagesPath - path to images
     * @param string $fileName - base files name without suffix and extension
     * @param ImageVersionConfig[] $imageVersionsConfigs
     * @return array
     */
    public static function getVersionsPaths(
        string $imagesPath,
        string $fileName,
        array $imageVersionsConfigs
    ): array {
        $originalFileName = $fileName;
        $ext = self::findFileExtension($imagesPath, $originalFileName);
        $result = [ImageVersionConfig::SOURCE_VERSION_NAME => ''];
        if ($ext) {
            $result[ImageVersionConfig::SOURCE_VERSION_NAME] = $imagesPath . $originalFileName . '.' . $ext;
        }
        foreach ($imageVersionsConfigs as $versionName => $imageVersionConfig) {
            if ($versionName !== ImageVersionConfig::SOURCE_VERSION_NAME) {
                $profileExt = $imageVersionConfig->isContentTypeConvertRequired()
                    ? self::getExtensionByContentType('image/' . $imageVersionConfig->getContentTypeToConvertTo())
                    : $ext;
                $result[$versionName] = $profileExt
                    ? $imagesPath . $fileName . $imageVersionConfig->getFileNameSuffix($versionName) . '.' . $profileExt
                    : '';
            }
        }
        return str_ireplace(['/', '\\'], DIRECTORY_SEPARATOR, $result);
    }
    
    /**
     * Find file extesion among allowed file extensions
     */
    protected static function findFileExtension(string $imagesPath, string $originalFileName): ?string
    {
        $ext = null;
        foreach (array_unique(self::$contentTypeToExtension) as $testExt) {
            if (File::exist(rtrim($imagesPath, '\\/') . DIRECTORY_SEPARATOR . $originalFileName . '.' . $testExt)) {
                $ext = $testExt;
                break;
            }
        }
        return $ext;
    }
    
    /**
     * Get regexp that will match required files
     */
    public static function getFileNamesRegexp(string $fileName): string
    {
        return "%^($fileName-.*?(\d+)x(\d+)|" . $fileName . ')%is';
    }
    
}
