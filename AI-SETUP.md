# 🤖 GutenBlock Pro - AI Setup

## Übersicht

GutenBlock Pro integriert KI-Textgenerierung direkt im Block-Editor.

### Features

- **Textgenerierung** für Paragraphen, Headlines, Buttons, Listen
- **Token-Tracking** mit monatlichem Limit (Free: 10.000 Tokens)
- **Lizenz-System** für unbegrenzte Nutzung (Lifetime)
- **Block-Prompts** automatisch von gutenblock.com geladen
- **System-Prompt** individuell anpassbar

---

## 🔑 API-Key konfigurieren

Der OpenAI API-Key ist **bereits im Plugin integriert** (obfuskiert).

### Standard-Verhalten

Das Plugin verwendet automatisch den eingebauten API-Key. Keine Konfiguration nötig.

### Optional: Eigenen Key verwenden

Falls du einen anderen Key nutzen möchtest:

**Option 1: Via Filter**
```php
add_filter( 'gutenblock_pro_openai_api_key', fn() => 'sk-proj-...' );
```

**Option 2: Via Datenbank**
```php
$ai_generator = GutenBlock_Pro_AI_Generator::get_instance();
$ai_generator->set_api_key( 'sk-proj-...' );
```

---

## 📊 Token-Limits

| Status | Tokens/Monat |
|--------|-------------|
| Ohne Lizenz | 10.000 |
| Mit Lifetime-Lizenz | Unbegrenzt |

Token-Verbrauch wird lokal in `wp_options` gespeichert und monatlich zurückgesetzt.

---

## 🔗 API-Endpoints

### Plugin REST-API (WordPress)

| Endpoint | Methode | Beschreibung |
|----------|---------|--------------|
| `/wp-json/gutenblock-pro/v1/ai/generate` | POST | Text generieren |
| `/wp-json/gutenblock-pro/v1/ai/usage` | GET | Token-Verbrauch |
| `/wp-json/gutenblock-pro/v1/prompts` | GET | Block-Prompts |
| `/wp-json/gutenblock-pro/v1/system-prompt` | GET | System-Prompt |

### SaaS-API (gutenblock.com)

| Endpoint | Methode | Beschreibung |
|----------|---------|--------------|
| `/api/v1/license/activate` | POST | Lizenz aktivieren |
| `/api/v1/license/verify` | POST | Lizenz prüfen |
| `/api/v1/license/deactivate` | POST | Lizenz deaktivieren |
| `/api/v1/plugin/prompts` | GET | Content-Field Prompts |

---

## 🎨 Editor-Integration

Die KI-Buttons erscheinen automatisch in der **Block-Sidebar** für:

- `core/paragraph`
- `core/heading`
- `core/button`
- `core/list-item`

### Block mit benanntem Prompt

Wenn ein Block ein `metadata.name` Attribut hat (z.B. `h1-home`), wird automatisch der passende Prompt aus der API geladen.

---

## 🛠️ Build

Nach Änderungen an `src/index.js`:

```bash
cd gutenblock-pro
npm run build
```

---

## 📁 Dateistruktur

```
gutenblock-pro/
├── inc/
│   ├── class-license.php       # Lizenzverwaltung
│   ├── class-ai-generator.php  # KI-Generierung
│   ├── class-ai-settings.php   # Admin-Einstellungen
│   └── ...
├── src/
│   ├── index.js                # Editor-Integration (React)
│   └── editor.scss             # Editor-Styles
├── assets/
│   ├── css/ai-settings.css     # Admin CSS
│   └── js/ai-settings.js       # Admin JS
└── build/                      # Kompilierte Assets
```
