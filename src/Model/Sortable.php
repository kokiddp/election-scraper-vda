<?php

namespace ElectionScraperVdA\Model;

/**
 * Represents a sortable collection.
 */
interface Sortable
{
  /**
   * Sorts the items in this collection.
   *
   * @return void
   */
  public function sort(): void;
}
