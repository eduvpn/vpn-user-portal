<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateInterval;
use DateTime;
use LC\Portal\Exception\GraphException;

class Graph
{
    /** @var \DateTime */
    private $dateTime;

    /** @var string|null */
    private $fontFile = null;

    /** @var int */
    private $fontSize = 10;

    /** @var array */
    private $imageSize = [600, 300];

    /** @var array */
    private $barColor = [0x55, 0x55, 0x55];

    /**
     * @param \DateTime $dateTime
     */
    public function __construct(DateTime $dateTime = null)
    {
        if (null === $dateTime) {
            $dateTime = new DateTime();
        }
        $this->dateTime = $dateTime;
    }

    /**
     * @param array<string> $fontList
     *
     * @return void
     */
    public function setFontList(array $fontList)
    {
        foreach ($fontList as $fontFile) {
            if (FileIO::exists($fontFile)) {
                $this->fontFile = $fontFile;

                return;
            }
        }

        throw new GraphException('none of the specified fonts were found');
    }

    /**
     * @param array<int> $barColor
     *
     * @return void
     */
    public function setBarColor(array $barColor)
    {
        $this->barColor = $barColor;
    }

    /**
     * @param array    $graphData where the key is the date of the format `Y-m-d`
     *                            and the value is the value to plot
     * @param callable $toHuman   a function to convert the values to human
     *                            readable form
     *
     * @return string the PNG logo data
     */
    public function draw(array $graphData, callable $toHuman = null, DateInterval $dateInterval = null)
    {
        if (null === $this->fontFile) {
            throw new GraphException('no font specified 1');
        }
        if (false === FileIO::exists($this->fontFile)) {
            throw new GraphException('specified font not found');
        }

        if (null === $dateInterval) {
            $dateInterval = new DateInterval('P1M');
        }

        if (null === $toHuman) {
            $toHuman = ['\LC\Portal\Graph', 'toHumanDummy'];
        }

        $dateList = $this->createDateList($dateInterval);

        // merge data
        foreach ($dateList as $k => $v) {
            if (\array_key_exists($k, $graphData)) {
                $dateList[$k] = $graphData[$k];
            }
        }

        $maxValue = $this->getMaxValue($dateList);
        $maxValue = 0 !== $maxValue % 2 ? $maxValue + 1 : $maxValue;

        $yAxisTopText = $toHuman($maxValue);
        $yAxisMiddleText = $toHuman($maxValue / 2);
        $yAxisTextWidth = max($this->textWidth($yAxisTopText), $this->textWidth($yAxisMiddleText));
        $yAxisTextHeight = max($this->textHeight($yAxisTopText), $this->textHeight($yAxisMiddleText));
        $relativeDateList = $this->toRelativeValues($dateList, $maxValue);

        // XXX loop over all text fields and determine MAX
        $xAxisTextHeight = $this->textHeight('2017-01-01');
        $xAxisTextWidth = $this->textWidth('2017-01-01') + 6;

        $xOffset = $yAxisTextWidth;
        $yOffset = $yAxisTextHeight / 2;

        // drawing lines etc is done with x,y starting at lower left bottom
        // drawing text is done with x,y starting at top left
        $img = imagecreatetruecolor($this->imageSize[0], $this->imageSize[1]);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

        $textColor = imagecolorallocate($img, 0x55, 0x55, 0x55);
        $barColor = imagecolorallocate($img, $this->barColor[0], $this->barColor[1], $this->barColor[2]);
        $lineColor = imagecolorallocate($img, 0xdd, 0xdd, 0xdd);

        // array imagettftext ( resource $image , float $size , float $angle , int $x , int $y , int $color , string $fontfile , string $text )
        // topText
        imagettftext(
            $img,
            $this->fontSize,
            0, // angle
            0, // x
            $yAxisTextHeight, // y
            $textColor,
            $this->fontFile,
            $yAxisTopText
        );

        $xAxisTotalBarSpace = $this->imageSize[0] - $xOffset;
        $numberOfBars = \count($relativeDateList);
        $xAxisSpacePerBar = $xAxisTotalBarSpace / $numberOfBars;
        $yAxisTotalBarSpace = $this->imageSize[1] - $yOffset - $xAxisTextWidth;

        // middleText
        imagettftext(
            $img,
            $this->fontSize,
            0, // angle
            0, // x
            $yAxisTextHeight + $yAxisTotalBarSpace / 2, // y
            $textColor,
            $this->fontFile,
            $yAxisMiddleText
        );

        // draw the horizonal grid lines
        for ($i = 0; $i < 4; ++$i) {
            imageline(
                $img,
                $xOffset,
                $yAxisTextHeight / 2 + $i * ($yAxisTotalBarSpace / 4),
                $this->imageSize[0],
                $yAxisTextHeight / 2 + $i * ($yAxisTotalBarSpace / 4),
                $lineColor
            );
        }

        $dateList = array_keys($relativeDateList);
        $valueList = array_values($relativeDateList);

        for ($i = 0; $i < $numberOfBars; ++$i) {
            $yPixels = $valueList[$i] * $yAxisTotalBarSpace;
            $this->drawBar(
                $img,
                $xOffset + $i * $xAxisSpacePerBar,
                $xAxisTextWidth,
                $xOffset + ($i + 1) * $xAxisSpacePerBar,
                $xAxisTextWidth + $yPixels,
                $barColor
            );

            // write xAxis dates
            if (0 === $i % 3) {
                imagettftext(
                    $img,
                    $this->fontSize,
                    90, // angle
                    $xOffset + $i * $xAxisSpacePerBar + $xAxisSpacePerBar / 2 + $xAxisTextHeight / 2,
                    $this->imageSize[1],
                    $textColor,
                    $this->fontFile,
                    $dateList[$i]
                );
            }
        }

        // buffer image output and return it as a value
        ob_start();
        imagepng($img);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($img);

        return $imageData;
    }

    /**
     * Create a list of dates from $dateInterval ago until now.
     *
     * @param \DateInterval $dateInterval
     *
     * @return array<string, int>
     */
    private function createDateList(DateInterval $dateInterval)
    {
        $currentDay = $this->dateTime->format('Y-m-d');
        $dateTime = clone $this->dateTime;
        $dateTime->sub($dateInterval);
        $oneDay = new DateInterval('P1D');

        $dateList = [];
        while ($dateTime < $this->dateTime) {
            $dateList[$dateTime->format('Y-m-d')] = 0;
            $dateTime->add($oneDay);
        }

        return $dateList;
    }

    /**
     * Get the maximum value in the dataset.
     *
     * @param array<string, int> $dateList
     *
     * @return int
     */
    private function getMaxValue(array $dateList)
    {
        $maxValue = 0;
        foreach ($dateList as $k => $v) {
            if ($v > $maxValue) {
                $maxValue = $v;
            }
        }

        return $maxValue;
    }

    /**
     * Convert the absolute values of the data to relative values, where the
     * highest value is converted to 1.
     *
     * @param array<string,int> $dateList
     * @param int               $maxValue
     *
     * @return array<string,float>
     */
    private function toRelativeValues(array $dateList, $maxValue)
    {
        if (0 !== $maxValue) {
            foreach ($dateList as $k => $v) {
                $dateList[$k] = $v / $maxValue;
            }
        }

        return $dateList;
    }

    /**
     * Determine the width of the box containing the text with the selected
     * font and size.
     *
     * @param string $textString
     *
     * @return int
     */
    private function textWidth($textString)
    {
        if (null === $this->fontFile) {
            throw new GraphException('no font specified');
        }
        if (false === $textBox = imagettfbbox($this->fontSize, 0, $this->fontFile, $textString)) {
            throw new GraphException('unable to determine width of text in box');
        }

        return $textBox[4];
    }

    /**
     * Determine the height of the box containing the text with the selected
     * font and size.
     *
     * @param string $textString
     *
     * @return int
     */
    private function textHeight($textString)
    {
        if (null === $this->fontFile) {
            throw new GraphException('no font specified');
        }
        if (false === $textBox = imagettfbbox($this->fontSize, 0, $this->fontFile, $textString)) {
            throw new GraphException('unable to determine height of text in box');
        }

        return -$textBox[5];
    }

    /**
     * @param resource $img
     * @param int      $x1
     * @param int      $y1
     * @param int      $x2
     * @param int      $y2
     * @param int      $color
     *
     * @return void
     */
    private function drawBar($img, $x1, $y1, $x2, $y2, $color)
    {
        // (0,0) is top left instead of bottom left
        imagefilledrectangle(
            $img,
            $x1 + 2,
            $this->imageSize[1] - $y1,
            $x2 - 2,
            $this->imageSize[1] - $y2,
            $color
        );
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private static function toHumanDummy($str)
    {
        return sprintf('%s ', $str);
    }
}
