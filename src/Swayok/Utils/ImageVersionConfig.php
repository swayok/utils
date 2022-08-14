<?php


namespace Swayok\Utils;


use Swayok\Utils\Exception\ImageVersionConfigException;

class ImageVersionConfig
{
    
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
     * @var null|string - 'jpeg', 'png', 'gif'
     */
    private $contentTypeToConvertTo = null;
    public const FORMAT_JPEG = 'jpeg';
    public const FORMAT_PNG = 'png';
    public const FORMAT_GIF = 'gif';
    private $jpegQuality = 90;
    private $pngQuality = 5;
    
    public const SOURCE_VERSION_NAME = 'source';
    
    public static function create()
    {
        return new static();
    }
    
    public function getWidth(): ?int
    {
        return $this->width;
    }
    
    /**
     * @return static
     * @throws ImageVersionConfigException
     */
    public function setWidth(?int $width)
    {
        if ($width !== null && (!ValidateValue::isFloat($width, true) || $width < 0)) {
            throw new ImageVersionConfigException('Width should be a positive number, 0 or null');
        }
        $this->width = (int)$width;
        return $this;
    }
    
    public function getHeight(): ?int
    {
        return $this->height;
    }
    
    /**
     * @return static
     * @throws ImageVersionConfigException
     */
    public function setHeight(?int $height)
    {
        if ($height !== null && (!ValidateValue::isFloat($height, true) || $height < 0)) {
            throw new ImageVersionConfigException('Height should be a positive integer, 0 or null');
        }
        $this->height = (int)$height;
        return $this;
    }
    
    public function isCropAllowed(): bool
    {
        return $this->allowCrop;
    }
    
    /**
     * @return static
     */
    public function allowCrop()
    {
        $this->allowCrop = true;
        return $this;
    }
    
    /**
     * @return static
     */
    public function disallowCrop()
    {
        $this->allowCrop = false;
        return $this;
    }
    
    public function isCenteringAllowed(): bool
    {
        return $this->allowCentering;
    }
    
    /**
     * @return static
     */
    public function allowCentering()
    {
        $this->allowCentering = true;
        return $this;
    }
    
    /**
     * @return static
     */
    public function disallowCentering()
    {
        $this->allowCentering = false;
        return $this;
    }
    
    public function isEnlargeAllowed(): bool
    {
        return $this->allowEnlarge;
    }
    
    /**
     * @return static
     */
    public function allowEnlarge()
    {
        $this->allowEnlarge = true;
        return $this;
    }
    
    /**
     * @return static
     */
    public function disallowEnlarge()
    {
        $this->allowEnlarge = false;
        return $this;
    }
    
    public function getContentTypeToConvertTo(): ?string
    {
        return $this->contentTypeToConvertTo;
    }
    
    public function isContentTypeConvertRequired(): bool
    {
        return !empty($this->contentTypeToConvertTo);
    }
    
    /**
     * @return static
     * @throws ImageVersionConfigException
     */
    public function setContentTypeToConvertTo(?string $convertToContentType)
    {
        $this->contentTypeToConvertTo = null;
        if (!empty($convertToContentType)) {
            if (!in_array($convertToContentType, [self::FORMAT_JPEG, self::FORMAT_GIF, self::FORMAT_PNG])) {
                throw new ImageVersionConfigException('Invalid image format passed');
            }
            $this->contentTypeToConvertTo = $convertToContentType;
        }
        return $this;
    }
    
    public function getJpegQuality(): int
    {
        return $this->jpegQuality;
    }
    
    /**
     * @return static
     * @throws ImageVersionConfigException
     */
    public function setJpegQuality(int $jpegQuality)
    {
        if (!ValidateValue::isInteger($jpegQuality, true) || $jpegQuality <= 0 || $jpegQuality > 100) {
            throw new ImageVersionConfigException('JPEQ image quality should be within 1 and 100');
        }
        $this->jpegQuality = $jpegQuality;
        return $this;
    }
    
    public function getPngQuality(): int
    {
        return $this->pngQuality;
    }
    
    /**
     * @return static
     * @throws ImageVersionConfigException
     */
    public function setPngQuality(int $pngQuality)
    {
        if (!ValidateValue::isInteger($pngQuality, true) || $pngQuality <= 0 || $pngQuality > 9) {
            throw new ImageVersionConfigException('PNG image quality should be within 1 and 9');
        }
        $this->pngQuality = $pngQuality;
        return $this;
    }
    
    public function getFileNameSuffix(string $versionName = ''): string
    {
        $optionsSummary = $this->getWidth() . 'x' . $this->getHeight() . '-'
            . ($this->isCropAllowed() ? 'crp' : 'ncrp') . '-' . ($this->isCenteringAllowed() ? 'cen' : 'ncen') . '-'
            . ($this->isEnlargeAllowed() ? 'enl' : 'nenl');
        return '-' . (empty($versionName) ? '' : $versionName . '-') . $optionsSummary;
    }
    
}