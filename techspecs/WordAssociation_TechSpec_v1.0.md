# MFSD Word Association — Technical Specification v1.0

**Plugin directory:** `mfsd-word-association/`
**Shortcode(s):** `[mfsd_word_association]`
**Shortcode attributes:** `category` (string, optional — filters words to a specific category), `timer` (integer, default 20 — countdown duration in seconds)
**Version:** 3.3.0
**Author:** MisterT9007
**Purpose:** A timed psychological exploration activity for MFSD students. A single word is presented and the student has a configurable number of seconds (default 20) to type 3 free-text associations. SteveGPT then generates a personalised 2-3 sentence insight about the student's relationship to the word. Results are saved and viewable in a history screen. Two operational modes: Mode 1 (unlimited random words) and Mode 2 (fixed ordered set of 1–5 admin-selected words, with completion tracked against course ordering). Integrates with the MFSD course ordering system for task sequencing.

---

## File Structure

| File | Purpose |
|------|---------|
| `mfsd-word-association.php` | Singleton class: activation/table creation with sample seed data, asset registration, shortcode, REST route registration and handlers, admin menu and page, AI summary generation and formatting |
| `assets/mfsd-word-association.css` | All frontend styles in dark gamer theme (CSS custom properties, Exo 2 + Nunito via Google Fonts) |
| `assets/mfsd-word-association.js` | Vanilla JS SPA: welcome screen, timed association screen with countdown timer, results screen, history screen, all-complete screen, error screen |
| `WORD_ASSOCIATION_DOCUMENTATION.md` | Developer documentation (earlier version, some details pre-date v3 features like Mode 2 and course management) |

---

## Database Schema

(Tables created in `register_activation_hook` → `install()`)

### wp_mfsd_flashcards_cards

Stores the word library. The table name reuses the `flashcards_cards` prefix from an earlier plugin design.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `word` | VARCHAR(100) NOT NULL | The word displayed to students |
| `category` | VARCHAR(100) NULL | Optional grouping (e.g. "Growth & Development") |
| `active` | TINYINT(1) NOT NULL DEFAULT 1 | 1 = available for selection, 0 = hidden |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** `idx_active`, `idx_category`

**Seed data:** 30 words across 5 categories are inserted on fresh activation if the table is empty: Growth & Development (Success, Failure, Challenge, Growth, Learning, Practice), Emotions & Feelings (Fear, Joy, Anxiety, Confidence, Hope, Pride), Relationships (Friendship, Trust, Communication, Teamwork, Support, Conflict), Future & Goals (Dream, Goal, Career, Ambition, Plan, Vision), Self & Identity (Identity, Strength, Weakness, Talent, Passion, Value).

### wp_mfsd_word_associations

Stores one row per completed word association exercise.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `user_id` | BIGINT UNSIGNED NOT NULL | WordPress user ID |
| `card_id` | BIGINT UNSIGNED NOT NULL | References `wp_mfsd_flashcards_cards.id` |
| `word` | VARCHAR(100) NOT NULL | Denormalised copy of the word (for display without joins) |
| `association_1` | TEXT NOT NULL | Student's first free-text association |
| `association_2` | TEXT NOT NULL | Student's second free-text association |
| `association_3` | TEXT NOT NULL | Student's third free-text association |
| `time_taken` | INT NOT NULL DEFAULT 0 | Seconds taken (wall-clock from word display to submit) |
| `ai_summary` | TEXT NULL | SteveGPT-generated insight |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** `idx_user` (user_id), `idx_card` (card_id), `idx_created` (created_at)

**Note:** Multiple rows per user are allowed (one per completed exercise). The plugin does NOT enforce one-per-word — a student in Mode 1 can associate the same word multiple times if it is randomly selected again.

---

## Key Flows

### Mode 1 — Random / Unlimited

1. Student visits a page with `[mfsd_word_association]`.
2. Shortcode checks MFSD Ordering gate: if task status is `locked`, returns locked message.
3. If status is `available`, `mfsd_set_task_status()` advances to `in_progress`.
4. JS calls `GET /history?limit=1`. If a previous result exists, the most recent results screen is shown immediately (skipping the welcome screen). Otherwise, the welcome screen is shown.
5. Student clicks "Start". JS calls `GET /word` (with optional category filter) — server picks a random `active` word.
6. Timed association screen renders: large countdown timer, progress bar, 3 labelled text inputs. Auto-focus moves through inputs on Enter. Timer changes colour at 10 s (orange/`wa-timer-low`) and at 5 s (red/`wa-timer-warning` with pulse animation). At 0 s, associations are submitted automatically.
7. Student submits (early or on timer expiry). JS calls `POST /save` — server generates AI summary, inserts row, notifies ordering system.
8. For Mode 1, `mfsd_set_task_status()` is called with `completed` immediately on first save (unlimited mode — first word unlocks the next task).
9. Results screen shows: word reminder, 3 association items, elapsed time, "Steve Says:" AI summary box, "Next Word" and "View History" buttons.
10. Student can do unlimited further words in Mode 1.

### Mode 2 — Fixed Word Set

1. Admin has configured Mode 2 in the admin panel with a specific set of 1–5 words and a word count.
2. On `GET /word`, server returns the next uncompleted word from the selected list in list order (determined by position in the `selected_words` option array, not by a sort column). If all words are complete, returns a 404 error.
3. JS calls `GET /history?limit=1` on init; if history exists the results screen shows with Mode 2 progress indicator ("Question N of M").
4. After each save, `completedWords` count is checked against `totalWords`. Completion only fires `mfsd_set_task_status('completed')` when `count(completed_ids) >= count(selected_words)`.
5. Between words, the "Next Word" button is shown only while `completedWords < totalWords`. When all are done, an "All Complete!" message appears with a "View Your History" button.
6. History button is shown only after 2+ completions in Mode 2.

### History Screen

JS calls `GET /history?limit=20`. Each past entry shows the word, date, 3 associations, and the AI summary. "Back" returns to the most recent result screen.

---

## AJAX / REST Endpoints

| Route | Method | Auth | Description |
|-------|--------|------|-------------|
| `/wp-json/mfsd-word-assoc/v1/word` | GET | Logged in | Returns next word. Mode 1: random active word (filtered by `category` query param if provided). Mode 2: next uncompleted word from admin-selected list. Response includes `mode`, `total_words` (Mode 2 only), `completed` count (Mode 2 only). Returns 404 if no words available or all Mode 2 words complete. |
| `/wp-json/mfsd-word-assoc/v1/save` | POST | Logged in | Accepts `card_id`, `word`, `association_1`, `association_2`, `association_3`, `time_taken`. Generates AI summary via SteveGPT, inserts row, notifies ordering system. Returns `summary`, `mode`, `total_words`, `completed`. |
| `/wp-json/mfsd-word-assoc/v1/history` | GET | Logged in | Returns up to `limit` (default 10, max passed by JS is 20) past associations for current user, ordered by `created_at DESC`. Also returns `mode`, `total_words`, `completed` count. |

**Nonce:** `X-WP-Nonce` header with `wp_create_nonce('wp_rest')` value passed from `MFSD_WA_CFG.nonce`. `permission_callback` returns `is_user_logged_in()` — no additional nonce check in the callback itself; WordPress core validates the REST nonce.

---

## Admin Panel

**Location:** WP Admin → Word Association (menu position 30, dashicons-editor-quote, `manage_options`)

The admin page combines all management on a single page:

### Mode Settings (top section)

| Setting | Option Key | Default | Notes |
|---------|-----------|---------|-------|
| Course Management | `mfsd_wa_course_management` | 1 (on) | Toggles integration with MFSD Ordering |
| Mode | `mfsd_wa_mode` | 1 | 1 = random unlimited, 2 = fixed set |
| Word Count | `mfsd_wa_word_count` | 1 | Number of words in Mode 2 set (1–5) |
| Selected Words | `mfsd_wa_selected_words` | [] | Array of `wp_mfsd_flashcards_cards.id` values |

Mode 2 word picker is shown/hidden via inline JavaScript based on the mode radio selection. The save button is disabled if selected word count doesn't match the configured word count. Saved via POST `action=save_mode_settings`, nonce `mfsd_word_assoc_mode_settings`.

**AI chatbot assignment note:** The admin page links to "SteveGPT → Plugin Integrations" for AI chatbot assignment — the chatbot ID is read from option `mfsd_stevegpt_map_word_association_summary` (with fallback to `mfsd_wa_chatbot_id`).

### Add New Word

Simple form (word + optional category with datalist autocomplete). POST `action=add_word`, nonce `mfsd_word_assoc_add_word`.

### Existing Words Table

All words with status and Activate/Deactivate toggle links (GET with per-item nonce `mfsd_word_assoc_toggle_{id}`) and Delete links (GET with per-item nonce `mfsd_word_assoc_delete_{id}`). Inactive rows shown at 50% opacity.

### Reset Student Answers

Dropdown of students who have at least one association record, with count and ordering status shown. On submit, deletes all rows from `wp_mfsd_word_associations` for the selected student and deletes their `word_association` row from `wp_mfsd_task_progress`. POST `action=reset_student`, nonce `mfsd_word_assoc_reset_student`.

---

## SteveGPT Integration

The plugin uses the `SteveGPT_Chatbot` class (not the `$GLOBALS['mwai']` pattern used in other plugins).

```php
$chatbot_id = get_option('mfsd_stevegpt_map_word_association_summary',
                  get_option('mfsd_wa_chatbot_id', ''));
$chatbot = SteveGPT_Chatbot::get($chatbot_id);
$prompt  = $chatbot->render_prompt([
    'word'          => $word,
    'association_1' => $assoc1,
    'association_2' => $assoc2,
    'association_3' => $assoc3,
    'time_taken'    => $time_taken . ' seconds',
]);
$summary = $chatbot->query($prompt, $user_id);
```

The chatbot configuration (system prompt, model, token limits) is managed in the SteveGPT admin under "Plugin Integrations". The option key `mfsd_stevegpt_map_word_association_summary` stores the assigned chatbot ID.

**Prompt variables available:** `{word}`, `{association_1}`, `{association_2}`, `{association_3}`, `{time_taken}`. The prompt template is defined in SteveGPT admin, not hardcoded here.

**Fallback:** If `SteveGPT_Chatbot::get()` or `query()` throws an exception, the fallback message `"I'm having trouble generating insights right now. Your associations have been saved!"` is stored and displayed.

**Output formatting** (`format_ai_summary()`): strips `###` headers, normalises bold markers, adds paragraph breaks around common section openers (e.g. "When you associate", "In conclusion"), ensures association headers are bolded, collapses excessive newlines.

---

## Assets

### `assets/mfsd-word-association.css`

Dark gamer theme matching the Junk Jobs palette. CSS custom properties on `.wa-wrap`. Key components:
- `.wa-wrap`, `.wa-card`: dark page/card containers with cyan border glow
- `.wa-title`, `.wa-subtitle`, `.wa-section-title`: typography
- `.wa-timer`: large 72px countdown, `wa-timer-low` (orange at ≤10 s), `wa-timer-warning` (red at ≤5 s with `wa-pulse` keyframe animation)
- `.wa-progress-bar`, `.wa-progress-fill`: 6px time-remaining progress bar with linear CSS transition
- `.wa-word-display`, `.wa-word`, `.wa-category`: prominent word presentation
- `.wa-inputs`, `.wa-input-group`, `.wa-input-label`, `.wa-input`: numbered 1-2-3 input fields
- `.wa-btn`, `.wa-secondary`, `.wa-btn-large`, `.wa-submit`: purple primary / cyan outline buttons
- `.wa-summary-box`, `.wa-summary-text`: AI insight display with purple-left-border styling
- `.wa-history-card`, `.wa-history-header`, `.wa-history-word`, `.wa-history-associations`, `.wa-history-assoc`, `.wa-history-summary`: history screen card layout
- `.wa-loading-overlay`, `.wa-spinner`: full-screen loading overlay
- `.wa-complete-message`: completion state box
- `.wa-progress-indicator`: "Question N of M" Mode 2 progress label
- `.wa-error`: error state box
- Responsive breakpoints at 768px and 480px

### `assets/mfsd-word-association.js`

Vanilla JS IIFE. Reads `window.MFSD_WA_CFG` for `userId`, `restUrl`, `nonce`, `category`, `timer`, `mode`, `wordCount`.

**State variables:** `currentMode`, `totalWords`, `completedWords`, `currentWord`, `timer` (setInterval ref), `timeRemaining`, `timeElapsed`, `startTime`.

**Screen flow:** `init()` → check history → `showWelcome()` or `showResults()` (last result) → `loadWord()` → `startAssociation()` → `submitAssociations()` → `showResults()` → `showHistory()` or `loadWord()` (next word) or `showAllComplete()`.

**Timer:** `setInterval` at 1000 ms. Updates countdown display and progress bar width. Triggers `submitAssociations()` automatically at 0. Timer classes applied at ≤10 s and ≤5 s thresholds.

**`formatSummaryForDisplay(text)`:** normalises bold markers (`**text**` → `<strong>`), handles edge cases like `:**` and `**:`, converts single `*` to `<em>`, strips remaining asterisks, converts newlines to `<br>`.

---

## Security

- All REST `permission_callback` functions return `is_user_logged_in()`.
- WordPress REST API validates `X-WP-Nonce` header on all requests.
- Admin form submissions use `check_admin_referer()` with action-specific nonce keys.
- Toggle and delete actions use per-item nonces (`mfsd_word_assoc_toggle_{id}`, `mfsd_word_assoc_delete_{id}`).
- All REST handler inputs use `sanitize_text_field()`, `sanitize_textarea_field()`, `intval()`.
- All `$wpdb` queries use `$wpdb->prepare()`.
- All admin output uses `esc_html()`, `esc_attr()`, `esc_url()`.
- User ID is always taken server-side from `get_current_user_id()`, never trusted from request parameters.
- AI summary is stored and rendered server-generated; no student text is rendered as raw HTML (associations are stored as text and set via `textContent` in JS or `esc_html()` in history display).

---

## Inter-Plugin Dependencies

| Plugin | Usage |
|--------|-------|
| `stevegtp` | `SteveGPT_Chatbot::get($chatbot_id)` and `$chatbot->query($prompt, $user_id)` for AI summary generation. Chatbot assigned via SteveGPT Plugin Integrations admin. Required for AI summaries; falls back gracefully if unavailable. |
| `mfsd-ordering` (utility plugin) | `mfsd_get_task_status($student_id, 'word_association')` for ordering gate; `mfsd_set_task_status()` to mark `in_progress` and `completed`; `mfsd_ordering_locked_message()` for locked display; `mfsd_get_task_order_row()` check before deleting `wp_mfsd_task_progress` on reset. Plugin works without it (course management checkbox warning shown in admin). |

---

## Version History

| Version | Changes |
|---------|---------|
| 3.3.0 | Current. Mode 2 (fixed word set, ordered, completion tracking). Course management / ordering gate. Admin reset student answers. Per-item nonces for toggle/delete. `SteveGPT_Chatbot::get()` integration pattern replacing direct API calls. |
| 2.x | Mode 1 only (random unlimited). Basic AI summary via direct MWAI/Anthropic call. |
| 1.0.0 | Initial release. 20-second timed word association, 3 free-text inputs, AI insight, history tracking. |
