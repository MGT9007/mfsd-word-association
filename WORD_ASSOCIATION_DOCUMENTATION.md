# MFSD Word Association Plugin - Development Documentation

## Overview

The MFSD Word Association plugin is a psychological exploration tool that helps users discover their personal connections to words through rapid association exercises. Unlike traditional flashcards, this plugin focuses on **spontaneous word associations** with AI-powered insights.

### Key Features
- **20-Second Timer**: Users have limited time to respond, capturing genuine first reactions
- **3 Free-Text Associations**: Open-ended responses rather than multiple choice
- **AI-Powered Insights**: Claude analyzes associations and provides personalized summaries
- **Visual Timer**: Large countdown display with progress bar
- **History Tracking**: View past associations and their AI insights
- **Simple Admin**: Easy word management interface

---

## How It Works

### User Flow

1. **Welcome Screen**
   - Clear instructions about the 20-second timer
   - Tips for authentic responses
   - Option to view past associations

2. **Word Display**
   - Large, prominent word appears on screen
   - 20-second countdown timer starts immediately
   - Visual progress bar shows time remaining
   - Category badge (optional)

3. **Input Phase**
   - Three text input fields (numbered 1, 2, 3)
   - Auto-focus moves to next field on Enter
   - Timer changes color as time runs out (blue → orange → red)
   - Can submit early or wait for timer to expire

4. **AI Analysis**
   - Loading screen while Claude API processes
   - AI generates 2-3 sentence insight about the user's relationship to the word
   - Focuses on emotional/personal connections rather than definitions

5. **Results Display**
   - Shows the original word
   - Lists all three associations
   - Displays completion time
   - Presents AI-generated insight in highlighted box
   - Options to continue with another word or view history

---

## Installation

1. **Upload Files**
   - `mfsd-word-association.php`
   - `mfsd-word-association.js`
   - `mfsd-word-association.css`

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate "MFSD Word Association"

3. **Configure API Key**
   - Go to Word Association → Settings
   - Enter your Anthropic API key
   - Get key from: https://console.anthropic.com/

4. **Database Tables**
   - `wp_mfsd_flashcards_cards` - Word storage
   - `wp_mfsd_word_associations` - User responses and AI summaries

5. **Sample Data**
   - 30 sample words automatically inserted across 5 categories

---

## Usage

### Basic Shortcode

```
[mfsd_word_association]
```

### With Category Filter

```
[mfsd_word_association category="Growth & Development"]
```

### Custom Timer

```
[mfsd_word_association timer="30"]
```

### Combined Parameters

```
[mfsd_word_association category="Emotions & Feelings" timer="15"]
```

---

## Database Schema

### Table: `wp_mfsd_flashcards_cards`

Stores the words that will be shown to users.

```sql
id (BIGINT) - Primary key
word (VARCHAR 100) - The word to display
category (VARCHAR 100) - Optional grouping
active (TINYINT) - Enable/disable word
created_at (DATETIME) - Creation timestamp
```

**Sample Data:**
- Growth & Development: Success, Failure, Challenge, Growth, Learning, Practice
- Emotions & Feelings: Fear, Joy, Anxiety, Confidence, Hope, Pride
- Relationships: Friendship, Trust, Communication, Teamwork, Support, Conflict
- Future & Goals: Dream, Goal, Career, Ambition, Plan, Vision
- Self & Identity: Identity, Strength, Weakness, Talent, Passion, Value

### Table: `wp_mfsd_word_associations`

Stores user responses and AI-generated insights.

```sql
id (BIGINT) - Primary key
user_id (BIGINT) - WordPress user ID
card_id (BIGINT) - References flashcards_cards.id
word (VARCHAR 100) - The word shown (denormalized)
association_1 (TEXT) - First association
association_2 (TEXT) - Second association
association_3 (TEXT) - Third association
time_taken (INT) - Seconds to complete
ai_summary (TEXT) - Claude-generated insight
created_at (DATETIME) - Completion timestamp
```

---

## REST API Endpoints

**Base URL:** `/wp-json/mfsd-word-assoc/v1/`

### GET `/word`

Get a random word for the association exercise.

**Query Parameters:**
- `category` (optional): Filter by category

**Response:**
```json
{
  "success": true,
  "word": {
    "id": 1,
    "word": "Success",
    "category": "Growth & Development",
    "active": 1
  }
}
```

### POST `/save`

Save user associations and generate AI summary.

**Body:**
```json
{
  "card_id": 1,
  "word": "Success",
  "association_1": "achievement",
  "association_2": "hard work",
  "association_3": "celebration",
  "time_taken": 12
}
```

**Response:**
```json
{
  "success": true,
  "summary": "For you, success seems deeply connected to the journey rather than just the destination. The mention of 'hard work' alongside 'celebration' suggests you value both the effort put in and the joy of achievement. Your association with 'achievement' indicates a results-oriented mindset balanced with an appreciation for the process."
}
```

**AI Summary Generation:**
- Uses Claude 3.5 Sonnet via Anthropic API
- Max 300 tokens per summary
- 30-second timeout
- Empathetic, personalized tone
- Focuses on emotional/psychological connections

### GET `/history`

Retrieve user's past associations.

**Query Parameters:**
- `limit` (optional): Number of records (default: 10)

**Response:**
```json
{
  "success": true,
  "history": [
    {
      "id": 123,
      "word": "Success",
      "association_1": "achievement",
      "association_2": "hard work",
      "association_3": "celebration",
      "time_taken": 12,
      "ai_summary": "...",
      "created_at": "2026-02-04 10:30:00"
    }
  ]
}
```

---

## Admin Interface

### Word Management

**Location:** WordPress Admin → Word Association

**Features:**
- Add new words with category
- View all existing words in table
- Delete words with confirmation
- Category autocomplete from existing categories
- Active/Inactive status display

**Add Word Form:**
```
Word: [text input - required]
Category: [text input with autocomplete - optional]
[Add Word button]
```

**Words Table:**
```
Word | Category | Status | Actions
-----|----------|--------|--------
Success | Growth & Development | Active | Delete
Fear | Emotions & Feelings | Active | Delete
```

### Settings

**Location:** Word Association → Settings

**API Configuration:**
```
Anthropic API Key: [password field]
[Save Settings button]
```

**Important Notes:**
- API key stored in wp_options table
- Required for AI summary generation
- Keep key secure - use password field type
- Test key by running a word association

---

## AI Summary Generation

### Prompt Structure

The AI receives this prompt:

```
The user was shown the word "{WORD}" and asked to quickly provide 3 word associations. They responded with:

1. {association_1}
2. {association_2}
3. {association_3}

Based on these associations, write a brief 2-3 sentence insight about what this word means to this person or how they relate to it. Be empathetic, thoughtful, and specific. Focus on the emotional or personal connection rather than dictionary definitions.
```

### Example Prompts & Responses

**Word: "Failure"**
Associations: "learning", "opportunity", "growth"

AI Summary: "Your associations reveal a remarkably growth-oriented relationship with failure. Rather than viewing it as something to fear or avoid, you see failure as a catalyst for learning and personal development. This mindset suggests resilience and the ability to extract valuable lessons from setbacks."

**Word: "Dream"**
Associations: "future", "scary", "exciting"

AI Summary: "Dreams carry a complex emotional weight for you - they represent both possibility and vulnerability. The simultaneous feelings of fear and excitement suggest you're someone who has meaningful aspirations but also recognizes the courage it takes to pursue them. This balanced perspective shows emotional maturity in facing the unknown."

### API Error Handling

**No API Key:**
```
"AI summary unavailable. Please configure your Anthropic API key in plugin settings."
```

**API Request Failed:**
```
"AI summary generation failed. Please try again."
```

**Invalid API Key:**
```
"AI summary generation failed. Please check your API key."
```

---

## JavaScript Architecture

### State Management

```javascript
let currentWord = null;      // Current word object from API
let timer = null;            // setInterval reference
let timeRemaining = 20;      // Countdown seconds
let timeElapsed = 0;         // Total time taken
let startTime = null;        // Timestamp when word displayed
```

### Key Functions

**Timer System:**
```javascript
function startTimer() {
  timer = setInterval(() => {
    timeRemaining--;
    updateTimerDisplay();
    updateProgressBar();
    checkTimerWarnings();
    if (timeRemaining <= 0) {
      submitAssociations();
    }
  }, 1000);
}
```

**Auto-Focus Flow:**
- Field 1 → Field 2 (on Enter)
- Field 2 → Field 3 (on Enter)
- Field 3 → Submit (on Enter)

**Validation:**
- At least one association required
- Empty fields saved as "(no response)"
- Timer submission works even with empty fields

### Visual Feedback

**Timer Colors:**
- 20-11 seconds: Blue (`#4a90e2`)
- 10-6 seconds: Orange (`#f39c12`)
- 5-0 seconds: Red (`#e74c3c`) with pulse animation

**Progress Bar:**
- Fills from 100% to 0%
- Blue gradient background
- 1-second linear transition
- Synchronized with timer

---

## CSS Architecture

### Color Scheme

**Primary Colors:**
- Blue: `#4a90e2` (timer, buttons, accents)
- Purple Gradient: `#667eea → #764ba2` (word display, summaries)
- White: Background and text areas

**Status Colors:**
- Normal: Blue `#4a90e2`
- Warning: Orange `#f39c12`
- Urgent: Red `#e74c3c`

**Neutral Colors:**
- Dark Text: `#2c3e50`
- Medium Text: `#666`, `#7f8c8d`
- Light Gray: `#f8f9fa`, `#e5e5e5`

### Animations

**Timer Pulse (< 5 seconds):**
```css
@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}
```

**Button Hover:**
```css
.wa-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(74, 144, 226, 0.3);
}
```

**Loading Spinner:**
```css
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
```

### Responsive Breakpoints

**Tablet (≤768px):**
- Reduced font sizes
- Full-width buttons
- Stacked layouts

**Mobile (≤480px):**
- Smaller timer display
- Compact word display
- Single-column history cards

---

## User Experience Details

### Timer Psychology

**Why 20 Seconds?**
- Short enough to prevent overthinking
- Long enough to type 3 responses
- Creates mild time pressure for authenticity
- Captures "gut reaction" associations

**Visual Countdown:**
- Large, prominent display
- Color changes create urgency
- Progress bar provides dual feedback
- Pulse animation at final seconds

### Input Field Design

**Numbered Labels:**
- Clear expectation of 3 responses
- Visual hierarchy (1, 2, 3)
- Encourages completion

**Auto-Focus:**
- Reduces friction between inputs
- Maintains flow state
- Keyboard-friendly (Enter to advance)

**Placeholder Text:**
- "Type your association..."
- Non-judgmental language
- Encourages openness

### AI Insight Presentation

**Design Choices:**
- Purple gradient box (stands out)
- Robot emoji (🤖) creates friendliness
- Italic text suggests reflection
- Prominent placement after associations

**Tone Guidelines:**
- Empathetic, not clinical
- Specific, not generic
- Insightful, not obvious
- 2-3 sentences (digestible)

---

## Customization Guide

### Changing Timer Duration

**In Shortcode:**
```
[mfsd_word_association timer="30"]
```

**In JavaScript:**
```javascript
const TIMER_DURATION = cfg.timer || 20;
```

### Adding Word Categories

**Via Admin:**
1. Go to Word Association admin
2. Type new category in Category field
3. Add words to that category

**Via Database:**
```php
$wpdb->insert($table, array(
    'word' => 'Innovation',
    'category' => 'Business Concepts',
    'active' => 1
));
```

### Customizing AI Prompt

Edit `generate_ai_summary()` in PHP file:

```php
$prompt = "The user was shown the word \"{$word}\"...";
// Modify prompt structure here
$prompt .= "Your custom instructions...";
```

### Styling Changes

**Timer Size:**
```css
.wa-timer {
  font-size: 72px; /* Change this */
}
```

**Color Scheme:**
```css
/* Find and replace color codes */
#4a90e2 → Your primary color
#667eea → Your gradient start
#764ba2 → Your gradient end
```

---

## Security Features

### Authentication
- All endpoints require WordPress login
- `check_permission()` validates user state
- No anonymous access to API

### Input Sanitization
- `sanitize_text_field()` for words/categories
- `sanitize_textarea_field()` for associations
- `intval()` for numeric values

### SQL Injection Prevention
- All queries use `$wpdb->prepare()`
- Parameterized statements throughout
- No string concatenation in queries

### API Key Security
- Stored in wp_options (not in code)
- Password field in admin (masked display)
- Never exposed to frontend JavaScript
- Used only in server-side API calls

### XSS Prevention
- `esc_html()` on all output
- `esc_attr()` for HTML attributes
- User input never directly rendered as HTML

---

## Troubleshooting

### AI Summaries Not Generating

**Check:**
1. API key configured in Settings
2. API key is valid (test at console.anthropic.com)
3. Server can reach api.anthropic.com
4. Check WordPress debug logs

**Test API Key:**
```php
// Add to functions.php temporarily
$key = get_option('mfsd_anthropic_api_key');
var_dump($key); // Should show key starting with 'sk-ant-'
```

### Words Not Loading

**Check:**
1. Database tables created (check phpMyAdmin)
2. At least one word with active=1
3. Category filter matches existing category
4. Check browser console for JS errors

**Verify Words:**
```sql
SELECT * FROM wp_mfsd_flashcards_cards WHERE active = 1;
```

### Timer Not Working

**Check:**
1. JavaScript loaded (view page source)
2. `MFSD_WA_CFG` object exists (browser console)
3. No JavaScript errors in console
4. Root element exists: `#mfsd-word-assoc-root`

### History Not Saving

**Check:**
1. User is logged in
2. Associations submitted successfully
3. Database table exists
4. Check for PHP errors in debug log

---

## Performance Considerations

### API Calls
- AI summary generation: ~2-5 seconds
- Rate limited by Anthropic (check your plan)
- Consider caching responses (not implemented)

### Database Queries
- Random word selection: Fast with proper indexing
- History retrieval: Limited to 10-20 records
- Indexes on: active, category, user_id, created_at

### Frontend Performance
- Timer runs at 1-second intervals
- Minimal DOM manipulation during countdown
- Loading overlay prevents duplicate submissions

---

## Future Enhancements

### Potential Features

1. **Multiple Timer Options**
   - Quick mode (10 seconds)
   - Standard mode (20 seconds)
   - Thoughtful mode (30 seconds)

2. **Association Patterns**
   - Track common word pairs
   - Identify personal themes
   - Generate word clouds

3. **Social Features**
   - Compare associations with friends
   - Anonymous group averages
   - Shared insights (opt-in)

4. **Advanced Analytics**
   - Response time analysis
   - Category preferences
   - Emotional patterns over time

5. **Gamification**
   - Daily challenges
   - Streaks for consistent use
   - Achievement badges
   - Leaderboards (optional)

6. **Export Options**
   - PDF report of all associations
   - CSV download of history
   - Share individual insights

7. **AI Enhancements**
   - Follow-up questions
   - Deeper analysis over time
   - Pattern recognition across sessions

---

## Testing Checklist

### Functional Testing
- [ ] Welcome screen displays correctly
- [ ] Word loads from database
- [ ] Timer counts down properly
- [ ] Color changes at 10s and 5s
- [ ] Progress bar syncs with timer
- [ ] Input fields accept text
- [ ] Auto-focus advances on Enter
- [ ] Submit works before timer expires
- [ ] Auto-submit when timer reaches 0
- [ ] AI summary generates correctly
- [ ] Results display all data
- [ ] History saves to database
- [ ] History displays past entries
- [ ] Navigation buttons work
- [ ] Category filtering works

### Error Handling
- [ ] Handles missing API key gracefully
- [ ] Shows error for invalid API key
- [ ] Handles API timeout
- [ ] Validates at least one association
- [ ] Handles empty word list
- [ ] Database connection errors caught

### Responsive Design
- [ ] Desktop layout (>768px)
- [ ] Tablet layout (768px)
- [ ] Mobile layout (480px)
- [ ] Timer readable on all sizes
- [ ] Buttons accessible on mobile

### Security
- [ ] Requires user login
- [ ] SQL injection protected
- [ ] XSS prevention working
- [ ] API key not exposed
- [ ] Nonce verification active

---

## Code Examples

### Custom Word Import

```php
// Bulk import words from array
function import_custom_words() {
    global $wpdb;
    $table = $wpdb->prefix . 'mfsd_flashcards_cards';
    
    $words = array(
        array('Innovation', 'Business'),
        array('Creativity', 'Business'),
        array('Strategy', 'Business')
    );
    
    foreach ($words as $word) {
        $wpdb->insert($table, array(
            'word' => $word[0],
            'category' => $word[1],
            'active' => 1
        ));
    }
}
```

### Hook Into Save Event

```php
// Add custom logic after associations saved
add_filter('mfsd_wa_before_save', function($data) {
    // Modify data before saving
    error_log('User ' . $data['user_id'] . ' completed: ' . $data['word']);
    return $data;
});
```

### Custom AI Prompt

```php
// Filter AI prompt before sending
add_filter('mfsd_wa_ai_prompt', function($prompt, $word, $associations) {
    $custom = "Additional context: Focus on creativity aspects.\n\n";
    return $custom . $prompt;
}, 10, 3);
```

---

## Support & Maintenance

### Regular Maintenance
- Monitor API usage (Anthropic console)
- Review error logs weekly
- Check database size quarterly
- Update API model version annually

### User Support
- Provide clear API key instructions
- Create video tutorial for first use
- FAQ section for common issues
- Contact form for technical problems

---

## License

This plugin follows WordPress plugin licensing standards.

---

**End of Documentation**

*Last Updated: February 2026*
*Plugin Version: 1.0.0*
*Author: MisterT9007*
*For: My Future Self Digital Platform*
