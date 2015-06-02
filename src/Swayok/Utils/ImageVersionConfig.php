<?php


namespace Swayok\Utils;


use Swayok\Utils\Exception\ImageVersionConfigException;

class ImageVersionConfig {

    /**
     * @var null|int - 0: no limit, fits to aspect ratio
     */
    private $width = null;
    /**
     * @var null|int - 0: no limit, fits to aspect ratio
     */
    private $height = null;

    private $allowCrop = false;
    private $allowCentering = true;
    private $allowEnlarge = false;

    /**
     * @var bool|string - 'jpeg', 'png', 'gif'
     */
    private $contentTypeToConvertTo = false;
    const FORMAT_JPEG = 'jpeg';
    const FORMAT_PNG = 'png';
    const FORMAT_GIF = 'gif';
    private $jpegQuality = 90;
    private $pngQuality = 5;

    const SOURCE_VERSION_NAME = 'source';

    static public function create() {
        return new ImageVersionConfig();
    }

    /**
     * @return int|null
     */
    public function getWidth() {
        return $this->width;
    }

    /**
     * @param int|null $width
     * @return $this
     * @throws ImageVersionConfigException
     */
    public function setWidth($width) {
        if ($width !== null && (!ValidateValue::isFloat($width, true) || $width < 0)) {
            throw new ImageVersionConfigException('Width should be a positive number, 0 or null');
        }
        $this->width = (int) $width;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getHeight() {
        return $this->height;
    }

    /**
     * @param int|null $height
     * @return $this
     * @throws ImageVersionConfigException
     */
    public function setHeight($height) {
        if ($height !== null && (!ValidateValue::isFloat($height, true) || $height < 0)) {
            throw new ImageVersionConfigException('Height should be a positive integer, 0 or null');
        }
        $this->height = (int) $height;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isCropAllowed() {
        return $this->allowCrop;
    }

    /**
     * @return $this
     */
    public function allowCrop() {
        $this->allowCrop = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disallowCrop() {
        $this->allowCrop = false;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isCenteringAllowed() {
        return $this->allowCentering;
    }

    /**
     * @return $this
     */
    public function allowCentering() {
        $this->allowCentering = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disallowCentering() {
        $this->allowCentering = false;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isEnlargeAllowed() {
        return $this->allowEnlarge;
    }

    /**
     * @return $this
     */
    public function allowEnlarge() {
        $this->allowEnlarge = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disallowEnlarge() {
        $this->allowEnlarge = false;
        return $this;
    }

    /**
     * @return bool|string
     */
    public function getContentTypeToConvertTo() {
        return $this->contentTypeToConvertTo;
    }

    /**
     * @return bool
     */
    public function isContentTypeConvertRequired() {
        return !empty($this->contentTypeToConvertTo);
    }

    /**
     * @param bool|string $convertToContentType
     * @return $this
     * @throws ImageVersionConfigException
     */
    public function setContentTypeToConvertTo($convertToContentType) {
        if (empty($convertToContentType)) {
            $this->contentTypeToConvertTo = false;
        } else if (!in_array($convertToContentType, array(self::FORMAT_JPEG, self::FORMAT_GIF, self::FORMAT_PNG))) {
            throw new ImageVersionConfigException('Invalid image format passed');
        }
        $this->contentTypeToConvertTo = $convertToContentType;
        return $this;
    }

    /**
     * @return int
     */
    public function getJpegQuality() {
        return $this->jpegQuality;
    }

    /**
     * @param int $jpegQuality
     * @return $this
     * @throws ImageVersionConfigException
     */
    public function setJpegQuality($jpegQuality) {
        if (!ValidateValue::isInteger($jpegQuality, true) || $jpegQuality <= 0 || $jpegQuality > 100) {
            throw new ImageVersionConfigException('JPEQ image quality should be within 1 and 100');
        }
        $this->jpegQuality = $jpegQuality;
        return $this;
    }

    /**
     * @return int
     */
    public function getPngQuality() {
        return $this->pngQuality;
    }

    /**
     * @param int $pngQuality
     * @return $this
     * @throws ImageVersionConfigException
     */
    public function setPngQuality($pngQuality) {
        if (!ValidateValue::isInteger($pngQuality, true) || $pngQuality <= 0 || $pngQuality > 9) {
            throw new ImageVersionConfigException('PNG image quality should be within 1 and 9');
        }
        $this->pngQuality = $pngQuality;
        return $this;
    }

    /**
     * @param string $versionName
     * @return string
     */
    public function getFileNameSuffix($versionName = '') {
        $optionsSummary = $this->getWidth() . 'x' . $this->getHeight() . '-'
            . ($this->isCropAllowed() ? 'crp' : 'ncrp') . '-' . ($this->isCenteringAllowed() ? 'cen' : 'ncen') . '-'
            . ($this->isEnlargeAllowed() ? 'enl' : 'nenl');
        return '-' . (empty($versionName) ? '' : $versionName . '-') . $optionsSummary;
    }

}