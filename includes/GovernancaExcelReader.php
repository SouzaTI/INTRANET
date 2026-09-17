<?php
declare(strict_types=1);

/**
 * Leitor XLSX mínimo para a migração da Governança.
 * Sem dependência externa: usa ZipArchive + DOMDocument.
 */

function govExcelNormalize(string $value): string {
    $value = trim($value);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false) {
        $value = $converted;
    }

    $value = strtolower($value);
    return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
}

function govExcelResolveZipPath(string $base, string $target): string {
    $target = str_replace('\\', '/', trim($target));

    if (str_starts_with($target, '/')) {
        return ltrim($target, '/');
    }

    $parts = explode('/', $base);
    array_pop($parts);

    foreach (explode('/', $target) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($parts);
        } else {
            $parts[] = $segment;
        }
    }

    return implode('/', $parts);
}

function govExcelLoadXml(string $xml, string $context): DOMDocument {
    $dom = new DOMDocument();

    $previous = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$ok) {
        throw new RuntimeException("XML inválido em {$context}.");
    }

    return $dom;
}

function govExcelNodeText(DOMNode $node): string {
    $text = '';
    $xpath = new DOMXPath($node->ownerDocument);
    $nodes = $xpath->query('.//*[local-name()="t"]', $node);

    if ($nodes) {
        foreach ($nodes as $t) {
            $text .= $t->textContent;
        }
    }

    return $text;
}

function govExcelColumnIndex(string $letters): int {
    $n = 0;

    foreach (str_split($letters) as $char) {
        $n = ($n * 26) + (ord($char) - 64);
    }

    return $n - 1;
}

function govExcelSheetRows(string $xml, array $sharedStrings): array {
    $dom = govExcelLoadXml($xml, 'worksheet');
    $xpath = new DOMXPath($dom);

    $cells = [];
    $maxRow = -1;
    $maxCol = -1;

    $cellNodes = $xpath->query('//*[local-name()="c"]');

    if (!$cellNodes) {
        return [];
    }

    foreach ($cellNodes as $cell) {
        if (!$cell instanceof DOMElement) {
            continue;
        }

        $ref = $cell->getAttribute('r');

        if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
            continue;
        }

        $col = govExcelColumnIndex($m[1]);
        $row = ((int) $m[2]) - 1;
        $type = $cell->getAttribute('t');

        $value = '';

        if ($type === 'inlineStr') {
            $value = govExcelNodeText($cell);
        } else {
            $vNode = $xpath->query('./*[local-name()="v"]', $cell)?->item(0);
            $raw = $vNode ? $vNode->textContent : '';

            if ($type === 's') {
                $value = $sharedStrings[(int) $raw] ?? '';
            } elseif ($type === 'b') {
                $value = $raw === '1' ? 'Sim' : 'Não';
            } else {
                $value = $raw;
            }
        }

        $cells[$row][$col] = trim((string) $value);
        $maxRow = max($maxRow, $row);
        $maxCol = max($maxCol, $col);
    }

    if ($maxRow < 0 || $maxCol < 0) {
        return [];
    }

    $rows = [];

    for ($r = 0; $r <= $maxRow; $r++) {
        $row = array_fill(0, $maxCol + 1, '');

        if (isset($cells[$r])) {
            foreach ($cells[$r] as $c => $value) {
                $row[$c] = $value;
            }
        }

        $rows[] = $row;
    }

    return $rows;
}

function govExcelRowsToObjects(array $rows): array {
    if (!$rows) {
        return [];
    }

    $headers = array_map(
        static fn($v) => trim((string) $v),
        array_shift($rows)
    );

    $objects = [];

    foreach ($rows as $row) {
        $hasValue = false;

        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                $hasValue = true;
                break;
            }
        }

        if (!$hasValue) {
            continue;
        }

        $item = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $item[$header] = trim((string) ($row[$index] ?? ''));
        }

        $objects[] = $item;
    }

    return $objects;
}

function govExcelReadWorkbook(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensão ZIP do PHP não está habilitada.');
    }

    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('A extensão DOM/XML do PHP não está habilitada.');
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Não foi possível abrir o arquivo XLSX.');
    }

    try {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('Estrutura interna do XLSX inválida.');
        }

        $relsDom = govExcelLoadXml($relsXml, 'workbook relationships');
        $relsXpath = new DOMXPath($relsDom);

        $relationshipMap = [];
        $relationshipNodes = $relsXpath->query('//*[local-name()="Relationship"]');

        if ($relationshipNodes) {
            foreach ($relationshipNodes as $rel) {
                if (!$rel instanceof DOMElement) {
                    continue;
                }

                $relationshipMap[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
            }
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $sharedDom = govExcelLoadXml($sharedXml, 'shared strings');
            $sharedXpath = new DOMXPath($sharedDom);
            $siNodes = $sharedXpath->query('//*[local-name()="si"]');

            if ($siNodes) {
                foreach ($siNodes as $si) {
                    $sharedStrings[] = govExcelNodeText($si);
                }
            }
        }

        $workbookDom = govExcelLoadXml($workbookXml, 'workbook');
        $workbookXpath = new DOMXPath($workbookDom);

        $sheets = [];
        $sheetNodes = $workbookXpath->query('//*[local-name()="sheet"]');

        if (!$sheetNodes) {
            return [];
        }

        foreach ($sheetNodes as $sheetNode) {
            if (!$sheetNode instanceof DOMElement) {
                continue;
            }

            $name = $sheetNode->getAttribute('name');
            $relationshipId = '';

            foreach ($sheetNode->attributes as $attr) {
                if ($attr->localName === 'id') {
                    $relationshipId = $attr->value;
                    break;
                }
            }

            if ($relationshipId === '' || empty($relationshipMap[$relationshipId])) {
                continue;
            }

            $target = govExcelResolveZipPath(
                'xl/workbook.xml',
                $relationshipMap[$relationshipId]
            );

            $sheetXml = $zip->getFromName($target);

            if ($sheetXml === false) {
                continue;
            }

            $sheets[$name] = govExcelSheetRows($sheetXml, $sharedStrings);
        }

        return $sheets;
    } finally {
        $zip->close();
    }
}

function govExcelFindSheet(array $sheets, string $wanted): array {
    $wantedNorm = govExcelNormalize($wanted);

    foreach ($sheets as $name => $rows) {
        if (govExcelNormalize((string) $name) === $wantedNorm) {
            return $rows;
        }
    }

    return [];
}

function govExcelBoolSim(string $value): int {
    $v = govExcelNormalize($value);
    return in_array($v, ['sim', 's', '1', 'true', 'ativo'], true) ? 1 : 0;
}

function govExcelCleanKeyPart(?string $value): string {
    $value = trim((string) $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return mb_strtolower($value, 'UTF-8');
}

function govExcelActivityHash(array $row): string {
    $parts = [
        govExcelCleanKeyPart($row['ID_ESTRUTURA'] ?? ''),
        govExcelCleanKeyPart($row['MACROPROCESSO'] ?? ''),
        govExcelCleanKeyPart($row['PROCESSO'] ?? ''),
        govExcelCleanKeyPart($row['ATIVIDADE'] ?? ''),
        govExcelCleanKeyPart($row['SUBATIVIDADE'] ?? ''),
    ];

    return hash('sha256', implode('|', $parts));
}
