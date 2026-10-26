<?php

namespace ErnestDefoe\InsertCoin\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property int $position
 */
class Category extends AbstractModel
{
    protected $table = 'insertcoin_categories';

    public $timestamps = false;

    protected $casts = ['id' => 'integer', 'position' => 'integer'];
}
