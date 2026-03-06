# Upgrade `laravel/ai` do `0.2.x`

> Cel: poszerzyć kompatybilność paczki `gracjankubicki/laravel-mcp-providers`
> z `laravel/ai` z obecnego `^0.1.2` do linii `0.2.x`, bez wpuszczania
> nieprzetestowanych przyszłych wersji `0.x`.

## 1. Stan obecny

- paczka wymaga:

```json
"laravel/ai": "^0.1.2"
```

- to blokuje instalację z `laravel/ai v0.2.x`
- aktualny najnowszy release `laravel/ai` to `v0.2.6`

## 2. Wniosek po analizie kompatybilności

Przejrzany kod paczki używa tylko wąskiego i stabilnego wycinka API `laravel/ai`:

- `Laravel\Ai\Contracts\Tool`
- `Laravel\Ai\Tools\Request`
- `Laravel\Ai\Contracts\HasTools`
- generowanie schem opartych o `Illuminate\Contracts\JsonSchema\JsonSchema`

Kluczowe miejsca w paczce:

- `src/Tools/AbstractMcpTool.php`
- `src/Concerns/HasMcpTools.php`
- `src/Generation/GeneratedToolRenderer.php`
- `src/Generation/SchemaCodeGenerator.php`

Porównanie `laravel/ai v0.1.5 -> v0.2.6` dla używanych kontraktów nie wykazało
breaking changes istotnych dla tej paczki:

- `Contracts/Tool` bez zmian semantycznych
- `Tools/Request` bez breaking changes
- `Contracts/HasTools` bez breaking changes
- `Promptable` zmienił tylko fallback exception, ale paczka na tym nie polega

Dodatkowo suchy test Composer resolution z lokalnie poszerzonym constraintem
przechodzi poprawnie dla:

- `laravel/framework ^12.0`
- `laravel/ai ^0.2.6`
- lokalnej kopii `gracjankubicki/laravel-mcp-providers`

## 3. Rekomendowana zmiana zależności

Najbezpieczniejsza zmiana:

```json
"laravel/ai": "^0.1.2 || ^0.2.0"
```

To daje:

- wsparcie dla istniejących projektów na `0.1.x`
- wsparcie dla nowych projektów na `0.2.x`
- brak automatycznego wpuszczania przyszłego `0.3.x`, które może być breaking

## 4. Czego nie robić

Nie ustawiać:

```json
"laravel/ai": "*"
```

ani:

```json
"laravel/ai": ">=0.1"
```

ani:

```json
"laravel/ai": "^0"
```

Bo przy wersjach `0.x` minor może być breaking. Taki constraint byłby zbyt szeroki
i otwierałby drogę na nieprzetestowane `0.3`, `0.4` itd.

## 5. Zakres wdrożenia

### W scope

- zmiana constraintu Composer
- weryfikacja testów paczki na `laravel/ai 0.1.x`
- weryfikacja testów paczki na `laravel/ai 0.2.x`
- dopięcie CI matrix dla obu linii

### Poza zakresem

- wspieranie przyszłych `0.3.x+` bez osobnej walidacji
- refactor kodu paczki bez potrzeby
- dodawanie nowych feature'ów niezwiązanych z kompatybilnością

## 6. Plan wdrożenia

1. Zmienić w `composer.json`:

```json
"laravel/ai": "^0.1.2 || ^0.2.0"
```

2. Uruchomić testy lokalnie na obecnym locku.

3. Dodać/zmodyfikować CI matrix tak, aby testować co najmniej:
- `laravel/ai ^0.1.2`
- `laravel/ai ^0.2.6`

4. W CI lub lokalnie wykonać:
- install/update dla każdej linii
- `lint`
- `analyse`
- `test`

5. Jeśli któraś linia wykryje regresję, dopiero wtedy wprowadzać minimalne poprawki
w kodzie paczki.

## 7. Proponowany matrix testowy

Minimalny wariant:

- PHP `8.5`
- Laravel `12`
- `laravel/ai`:
  - `^0.1.2`
  - `^0.2.6`

Opcjonalnie później:

- test na `laravel/framework ^13` gdy ten zakres będzie istotny dla adopcji paczki

## 8. Testy, które szczególnie warto dopilnować

- generowanie klas tooli z manifestu
- `AbstractMcpTool::handle(Request $request)`
- `HasMcpTools::tools()`
- rejestr `GeneratedToolRegistry`
- komendy:
  - `ai-mcp:generate`
  - `ai-mcp:discover`
  - `ai-mcp:sync`

## 9. Definition of Done

- `composer.json` wspiera `^0.1.2 || ^0.2.0`
- testy przechodzą dla obu linii `laravel/ai`
- CI matrix pokrywa obie wspierane linie
- nie ma zmian funkcjonalnych poza kompatybilnością zależności

## 10. Werdykt

Na podstawie obecnej analizy:

- sam constraint jest zbyt wąski
- kod paczki wygląda na kompatybilny z `laravel/ai 0.2.x`
- rekomendowana zmiana to poszerzenie do:

```json
"laravel/ai": "^0.1.2 || ^0.2.0"
```
