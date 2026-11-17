<?php

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;

arch('debugging calls never reach production')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('env() is read in config files only')                       // under config:cache, env() returns null
    ->expect('env')
    ->not->toBeUsed();

arch('controllers only speak HTTP: no queries, no transactions')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('models are Eloquent models')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

it('keeps no static state in app classes, which long-running workers would keep between jobs', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in(__DIR__ . '/../../app')->name('*.php') as $file) {
        $class = 'App\\' . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class) && ! trait_exists($class)) {
            continue;                                            // interfaces and enums have no properties
        }

        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if ($property->getDeclaringClass()->getName() === $class) {
                $offenders[] = "{$class}::\${$property->getName()}";
            }
        }
    }

    expect($offenders)->toBe([]);
});
