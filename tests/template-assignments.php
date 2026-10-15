<?php
/** Configuration compatibility and collision-free serialization coverage. @since 1.7.0 */
require dirname(__DIR__) . '/src/Services/GallerySettings.php';
use Seiger\sGallery\Services\GallerySettings;

$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$same = static function (array $expected, array $actual) use ($check): void {
    ksort($expected); ksort($actual);
    $check($expected === $actual, 'Template assignments changed');
};
$same([1=>[],3=>[],5=>[]], GallerySettings::assignments([1,3,5]));
$same([1=>['slider','top-sales'],3=>[],5=>[]], GallerySettings::assignments([1=>['slider','top-sales'],3,5]));
$legacy = [1,3,[5=>['block 1','block 2']], 'watermark'=>['file'=>'mark.png']];
$same([1=>[],3=>[],5=>['block 1','block 2']], GallerySettings::assignments($legacy));
$check(GallerySettings::hasLegacyAssignments($legacy), 'Legacy nested format is marked deprecated');
$check(!GallerySettings::hasLegacyAssignments([5=>['block 1'],1,3,'watermark'=>['file'=>'mark.png']]), 'Named settings are not mistaken for legacy assignments');
$cases = [
    [1=>[],3=>[],5=>[]],
    [1=>['slider'],3=>[],5=>[]],
    [1=>[],2=>[],3=>['slider']],
    [2=>['slider'],1=>[],3=>['footer'],4=>[]],
    [1=>[],3=>[]],
    [],
];
foreach ($cases as $assignments) {
    $settings = GallerySettings::withAssignments(['manual'=>['keep'=>true]], $assignments);
    $exported = GallerySettings::export($settings);
    $decoded = eval('return ' . $exported . ';');
    $check($decoded === $settings, 'PHP numeric keys survive export');
    $same($assignments, GallerySettings::assignments($decoded));
    $check($decoded['manual']['keep'] === true, 'Unrelated settings preserved');
    $check(!GallerySettings::hasLegacyAssignments($decoded), 'Writer must never emit deprecated nesting');
}
$plain = GallerySettings::export([1,3,5]);
$check(!str_contains($plain, '=>'), 'Simple configuration remains a plain ID list');
$named = GallerySettings::export([1=>['slider','top-sales']]);
$check(str_contains($named, "1 => [") && !str_contains($named, '0 =>'), 'Named tabs use one mapping level and plain block lists');
echo "sGallery template assignment compatibility/export OK\n";
