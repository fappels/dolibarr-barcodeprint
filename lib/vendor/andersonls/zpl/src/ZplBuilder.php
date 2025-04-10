<?php

namespace Zpl;

use Zpl\Commands\GraphicField;

class ZplBuilder extends AbstractBuilder
{
    /**
     * ZPL commands
     *
     * @var array
     */
    protected $commands = array();

    /**
     * Commands to be inserted before beginning of ZPL document (^XA)
     *
     * @var array
     */
    protected $preCommands = array();

    /**
     * Commands to be inserted after end of ZPL document (^XZ)
     *
     * @var array
     */
    protected $postCommands = array();

    /**
     * Resolution of the printer in DPI
     *
     * @var int
     */
    protected $resolution = 203;

    /**
     * @var Fonts\AbstractMapper
     */
    protected $fontMapper;

    const PAGE_SEPARATOR = '%PAGE_SEPARATOR%';

    /**
     *
     * @param string  $unit
     * @param int     $resolution Resolution of the document
     *
     * @throws BuilderException
     */
    public function __construct(string $unit = 'dots', int $resolution = 203)
    {
        parent::__construct($unit);
        $this->resolution = $resolution;
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::setFont()
     */
    public function setFont(string $font, float $size, ?float $width = null) : void
    {
        $fontMapper = $this->fontMapper;
        $mapper = $fontMapper::$mapper;
        if (isset($mapper[$font])) {
            $font = $mapper[$font];
        }
        $size = $size * ($this->resolution * 0.014);
        $command = '^CF' . $font . ',' . $size;

        if ($width !== null) {
            $width = $width * ($this->resolution * 0.014);
            $command .= ',' . $width;
        }
        $this->commands[] = $command;
    }

    /**
     * Value from 0 to 36.
     *
     * @param int $code
     */
    public function setEncoding(int $code) : void
    {
        $this->commands[] = '^CI' . $code;
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawText()
     */
    public function drawText(float $x, float $y, string $text, string $orientation = 'N', bool $invert = false) : void
    {
        $this->commands[] = '^FW' . $orientation;
        $this->commands[] = '^FO' . $this->toDots($x) . ',' . $this->toDots($y);
        if ($invert === true) {
            $this->commands[] = '^FR';
        }
        $this->commands[] = '^FD' . $text . '^FS';
        $this->commands[] = '^FWN';
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawLine()
     */
    public function drawLine(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $thickness = 0,
        string $color = 'B',
        bool $invert = false
    ) : void {
        $this->drawRect(
            $this->x,
            $this->y,
            $x2-$x1,
            $y2-$y1,
            $thickness,
            $color,
            0,
            $invert
        );
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawRect()
     */
    public function drawRect(
        float $x,
        float $y,
        float $width,
        float $height,
        float $thickness = 0,
        string $color = 'B',
        float $round = 0,
        bool $invert = false
    ) : void {
        $thickness = $thickness === 0 ? 3 : $this->toDots($thickness);
        $this->commands[] = '^FO' . $this->toDots($x) . ',' . $this->toDots($y)
                          . ($invert === true ? '^FR' : '')
                          . '^GB' . $this->toDots($width) . ',' . $this->toDots($height) . ',' . $thickness . ',' . $color . ',' . $round
                          . '^FS';
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawCircle()
     */
    public function drawCircle(
        float $x,
        float $y,
        float $diameter,
        float $thickness = 0,
        string $color = 'B',
        bool $invert = false
    ) : void {
        $thickness = $thickness === 0 ? 3 : $this->toDots($thickness);
        $this->commands[] = '^FO' . $this->toDots($x) . ',' . $this->toDots($y)
                          . ($invert === true ? '^FR' : '')
                          . '^GC' . $this->toDots($diameter) . ',' . $thickness . ',' . $color
                          . '^FS';
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawCell()
     */
    public function drawCell(
        float $width,
        float $height,
        string $text,
        bool $border = false,
        bool $ln = false,
        string $align = ''
    ) : void {
        $x = $this->getX();
        $y = $this->getY();
        if ($border === true) {
            $this->drawRect($x, $y, $width, $height);
        }
        if ($text !== '') {
            $offsetX = 10;
            $offsetY = (int) ($this->toDots($height) / 4);
            $this->commands[] = '^FO' . ($this->toDots($x) + $offsetX) . ',' . ($this->toDots($y) + $offsetY);
            if ($align !== '') {
                $this->commands[] = '^FB' . ($this->toDots($width) - $offsetX) . ',' . ($this->toDots($height) - $offsetY) . ',0,' . $align;
            }
            $this->commands[] = '^FD' . $text . '^FS';
        }
        if ($ln === true) {
            $this->setY($y + $height) ;
            $this->setX($this->getMargin());
        } else {
            $this->setX($x + $width);
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawCode128()
     */
    public function drawCode128(float $x, float $y, float $width, float $height, string $data, bool $printData = false, string $orientation = 'N', string $align = '') : void
    {
        $validOrientations = ['N', 'R', 'I', 'B'];
        if (in_array($orientation, $validOrientations) === false) {
            throw new \InvalidArgumentException('Valid values for orientation are: ' . implode(',', $validOrientations));
        }

        $offsetX = 0;
        if ($align === 'C') {
            $dataWithoutControl = str_replace(array(">;", ">6", ">8", ">", "_"), "", $data);
            $offsetX = round(($this->toDots($width) - ((strlen($dataWithoutControl) * 9) + 13) * 2) / 2);
        }
        $this->commands[] = '^FO' . ($this->toDots($x) + $offsetX) . ',' . $this->toDots($y);
        $this->commands[] = '^BC' . $orientation . ',' . $this->toDots($height) . ',' . ($printData === true ? 'Y' : 'N') . ',N,N,N';
        $this->commands[] = '^FD' . $data . '^FS';
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawQrCode()
     */
    public function drawQrCode(float $x, float $y, string $data, int $size = 10) : void
    {
        $this->commands[] = '^FO' . $this->toDots($x) . ',' . $this->toDots($y);
        $this->commands[] = '^BQN,2,' . $size;
        $this->commands[] = '^FD' . $data . '^FS';
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawDataMatrix()
     */
    public function drawDataMatrix(float $x, float $y, string $data, int $height = 6, string $orientation = 'N') : void
    {
        $this->commands[] = '^FO' . $this->toDots($x) . ',' . $this->toDots($y);
        $this->commands[] = '^BX' . $orientation . ',' . $height . ',' . '200,20,20,1,_,1';
        $this->commands[] = '^FD' . $data . '^FS';
    }

    /**
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawEAN13()
     */
    public function drawEAN13(float $x, float $y, float $width, float $height, string $data, bool $printData = false, string $orientation = 'N', string $align = '') : void
    {
        $validOrientations = ['N', 'R', 'I', 'B'];
        if (in_array($orientation, $validOrientations) === false) {
            throw new \InvalidArgumentException('Valid values for orientation are: ' . implode(',', $validOrientations));
        }

        $offsetX = 0;
        if ($align === 'C') {
            $dataWithoutControl = str_replace(array(">", "_"), "", $data);
            $offsetX = round(($this->toDots($width) - ((strlen($dataWithoutControl) * 7) + 7) * 2) / 2);
        }
        $this->commands[] = '^FO' . ($this->toDots($x) + $offsetX) . ',' . $this->toDots($y);
        $this->commands[] = '^BE' . $orientation . ',' . $this->toDots($height) . ',' . ($printData === true ? 'Y' : 'N') . ',N,N,A';
        $this->commands[] = '^FD' . $data . '^FS';
    }

    /**
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::drawGraphic()
     */
    public function drawGraphic(float $x, float $y, string $image, int $width) : void
    {
        $gf = new GraphicField();

        $this->commands[] = '^FO' . $this->toDots($x) . ',' . $this->toDots($y);
        $this->commands[] = $gf->createCommand($image, $width);
        $this->commands[] = '^FS';
    }

    /**
     * Adds an arbitrary command to the command queue
     *
     * @param string $command
     */
    public function addCommand(string $command) : void
    {
        $this->commands[] = $command;
    }

    /**
     *
     * @param string $command
     */
    public function addPreCommand(string $command) : void
    {
        $this->preCommands[] = $command;
    }

    /**
     *
     * @param array $commands
     */
    public function setPreCommands(array $commands) : void
    {
        $this->preCommands = $commands;
    }

    /**
     *
     * @param string $command
     */
    public function addPostCommand(string $command) : void
    {
        $this->postCommands[] = $command;
    }

    /**
     *
     * @param array $commands
     */
    public function setPostCommands(array $commands) : void
    {
        $this->postCommands = $commands;
    }

    /**
     * Adds a new label
     *
     * {@inheritDoc}
     * @see \Zpl\AbstractBuilder::newPage()
     */
    public function newPage() : void
    {
        $this->commands[] = '^XZ';
        $this->commands[] = self::PAGE_SEPARATOR;
        $this->commands[] = '^XA';
        $this->setY(0);
        $this->setX($this->getMargin());
    }

    /**
     * Converts the $size from $this->unit to dots
     *
     * @param float $size
     *
     * @return int The size in dots
     */
    protected function toDots(float $size) : float
    {
        switch ($this->unit) {
            case 'mm':
                //1 inch = 25.4 mm
                $sizeInDots = $size * $this->resolution / 25.4;
                break;
            default:
                $sizeInDots = $size;
                break;
        }
        return (int) $sizeInDots;
    }

    public function setFontMapper(Fonts\AbstractMapper $mapper) : void
    {
        $this->fontMapper = $mapper;
    }

    /**
     * Convert instance to ZPL.
     *
     * @return string
     */
    public function toZpl() : string
    {
        $preCommands = array_merge($this->preCommands, array('^XA'));
        $postCommands = array_merge(array('^XZ'), $this->postCommands, array(''));

        $zpl = implode("\&", array_merge($preCommands, $this->commands, $postCommands));
        $commands = implode("\&", array_merge($this->postCommands, $this->preCommands));
        $zpl = str_replace(self::PAGE_SEPARATOR, $commands, $zpl);
        return $zpl;
    }

    /**
     * Convert instance to string.
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->toZpl();
    }

    /**
     * Reset the command queue
     *
     * @return void
     */
    public function reset() : void
    {
        $this->commands = [];
        $this->preCommands = [];
        $this->postCommands = [];
    }
}
