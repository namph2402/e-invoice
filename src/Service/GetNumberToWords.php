<?php

namespace Drupal\e_invoice\Service;

/**
 * Invoice get number to words.
 */
class GetNumberToWords {

  /**
   * Handle invoice.
   *
   * @param int|float $number
   *   Xml data.
   *
   * @return string
   *   The string words.
   */
  public function handle(int|float $number) {
    if (!is_numeric($number)) {
      return FALSE;
    }
    $text_amount = $this->convertNumberToWords($number);
    $text_amount[0] = strtoupper($text_amount[0]);
    return $text_amount . ' đồng';
  }

  /**
   * {@inheritDoc}
   */
  private function convertNumberToWords($number) {
    $number = str_replace(',', '', trim($number));

    if (!is_numeric($number)) {
      return FALSE;
    }

    $dictionary = [
      0 => 'không',
      1 => 'một',
      2 => 'hai',
      3 => 'ba',
      4 => 'bốn',
      5 => 'năm',
      6 => 'sáu',
      7 => 'bảy',
      8 => 'tám',
      9 => 'chín',
    ];

    $units = [
      '', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ',
    ];

    $fraction = NULL;
    if (strpos($number, '.') !== FALSE) {
      [$number, $fraction] = explode('.', $number);
    }

    $number = (int) $number;

    if ($number === 0) {
      $words = 'không';
    }
    else {
      $words = '';
      $unitIndex = 0;

      while ($number > 0) {
        $chunk = $number % 1000;

        if ($chunk > 0) {
          $words = $this->readChunk($chunk, $dictionary) . ' ' . $units[$unitIndex] . ' ' . $words;
        }

        $number = intdiv($number, 1000);
        $unitIndex++;
      }

      $words = trim(preg_replace('/\s+/', ' ', $words));
    }

    if ($fraction !== NULL && $fraction !== '0') {
      $words .= ' phẩy ';
      $digits = str_split($fraction);
      foreach ($digits as $digit) {
        $words .= $dictionary[$digit] . ' ';
      }
      $words = trim($words);
    }

    return $words;
  }

  /**
   * {@inheritDoc}
   */
  private function readChunk($number, $dictionary) {
    $text = '';

    $hundreds = intdiv($number, 100);
    $tens = intdiv($number % 100, 10);
    $ones = $number % 10;

    if ($hundreds > 0) {
      $text .= $dictionary[$hundreds] . ' trăm';
    }

    if ($tens > 0) {
      if ($tens == 1) {
        $text .= ' mười';
      }
      else {
        $text .= ' ' . $dictionary[$tens] . ' mươi';
      }
    }
    elseif ($hundreds > 0 && $ones > 0) {
      $text .= ' linh';
    }

    if ($ones > 0) {
      if ($ones == 1 && $tens > 1) {
        $text .= ' mốt';
      }
      elseif ($ones == 5 && $tens >= 1) {
        $text .= ' lăm';
      }
      else {
        $text .= ' ' . $dictionary[$ones];
      }
    }

    return trim($text);
  }

}
