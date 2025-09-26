<?php

namespace ElectionScraperVdA\Model;

/**
 * Represents a summarizable collection.
 */
interface Summarizable
{
  /**
   * Returns a summary of the collection.
   *
   * @return string
   */
  public function getSummary(): string;
}
