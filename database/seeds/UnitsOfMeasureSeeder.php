<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class UnitsOfMeasureSeeder extends AbstractSeed
{
    public function run(): void
    {
        $units = [
            // Count / quantity
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['name' => 'Unit', 'abbreviation' => 'unit'],
            ['name' => 'Pair', 'abbreviation' => 'pr'],
            ['name' => 'Dozen', 'abbreviation' => 'dz'],
            ['name' => 'Set', 'abbreviation' => 'set'],

            // Packaging
            ['name' => 'Pack', 'abbreviation' => 'pk'],
            ['name' => 'Box', 'abbreviation' => 'bx'],
            ['name' => 'Carton', 'abbreviation' => 'ctn'],
            ['name' => 'Case', 'abbreviation' => 'cs'],
            ['name' => 'Bundle', 'abbreviation' => 'bdl'],
            ['name' => 'Roll', 'abbreviation' => 'rl'],
            ['name' => 'Pallet', 'abbreviation' => 'plt'],
            ['name' => 'Bag', 'abbreviation' => 'bag'],
            ['name' => 'Bottle', 'abbreviation' => 'btl'],
            ['name' => 'Can', 'abbreviation' => 'can'],
            ['name' => 'Jar', 'abbreviation' => 'jar'],
            ['name' => 'Tube', 'abbreviation' => 'tube'],
            ['name' => 'Sheet', 'abbreviation' => 'sht'],
            ['name' => 'Ream', 'abbreviation' => 'rm'],

            // Weight
            ['name' => 'Kilogram', 'abbreviation' => 'kg'],
            ['name' => 'Gram', 'abbreviation' => 'g'],
            ['name' => 'Milligram', 'abbreviation' => 'mg'],
            ['name' => 'Metric Ton', 'abbreviation' => 't'],
            ['name' => 'Pound', 'abbreviation' => 'lb'],
            ['name' => 'Ounce', 'abbreviation' => 'oz'],

            // Volume
            ['name' => 'Liter', 'abbreviation' => 'L'],
            ['name' => 'Milliliter', 'abbreviation' => 'mL'],
            ['name' => 'Gallon', 'abbreviation' => 'gal'],
            ['name' => 'Fluid Ounce', 'abbreviation' => 'fl oz'],

            // Length
            ['name' => 'Meter', 'abbreviation' => 'm'],
            ['name' => 'Centimeter', 'abbreviation' => 'cm'],
            ['name' => 'Millimeter', 'abbreviation' => 'mm'],
            ['name' => 'Kilometer', 'abbreviation' => 'km'],
            ['name' => 'Inch', 'abbreviation' => 'in'],
            ['name' => 'Foot', 'abbreviation' => 'ft'],
            ['name' => 'Yard', 'abbreviation' => 'yd'],

            // Area
            ['name' => 'Square Meter', 'abbreviation' => 'm2'],
            ['name' => 'Square Foot', 'abbreviation' => 'ft2'],
        ];

        foreach ($units as $unit) {
            $existing = $this->fetchRow(
                "SELECT id FROM unitsOfMeasure WHERE name = '" . addslashes($unit['name']) . "'"
            );

            if (!$existing) {
                $this->execute(
                    "INSERT INTO unitsOfMeasure (name, abbreviation) VALUES ('"
                        . addslashes($unit['name']) . "', '"
                        . addslashes($unit['abbreviation']) . "')"
                );
            }
        }
    }
}
