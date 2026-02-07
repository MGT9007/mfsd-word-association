(function () {
  console.log('MFSD_WA_CFG', window.MFSD_WA_CFG);
  const cfg = window.MFSD_WA_CFG || {};
  const root = document.getElementById("mfsd-word-assoc-root");
  if (!root) return;

  const TIMER_DURATION = cfg.timer || 20; // 20 seconds defaults
  
  // NEW: Mode tracking
  let currentMode = 1; // 1 = random unlimited, 2 = fixed set
  let totalWords = 0; // Total words in Mode 2
  let completedWords = 0; // Completed words in Mode 2
  let currentWord = null;
  let timer = null;
  let timeRemaining = TIMER_DURATION;
  let timeElapsed = 0;
  let startTime = null;

  const el = (t, c, txt) => {
    const n = document.createElement(t);
    if (c) n.className = c;
    if (txt !== undefined) n.textContent = txt;
    return n;
  };

  // ==================== UTILITY FUNCTIONS ====================

  function showLoading(message = 'Loading...') {
    const overlay = el('div', 'wa-loading-overlay');
    const spinner = el('div', 'wa-spinner');
    const text = el('div', 'wa-loading-text', message);
    overlay.appendChild(spinner);
    overlay.appendChild(text);
    document.body.appendChild(overlay);
    return overlay;
  }

  function hideLoading(overlay) {
    if (overlay && overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
    }
  }

  async function apiCall(endpoint, method = 'GET', body = null) {
    const options = {
      method,
      headers: {
        'X-WP-Nonce': cfg.nonce,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    };

    if (body) {
      options.body = JSON.stringify(body);
    }

    try {
      const res = await fetch(cfg.restUrl + endpoint, options);
      if (!res.ok) throw new Error('API request failed');
      return await res.json();
    } catch (err) {
      console.error('API Error:', err);
      throw err;
    }
  }

  // ==================== MAIN FLOW ====================

  async function init() {
    // Check if user has any previous associations
    const loading = showLoading('Loading...');
    
    try {
      const data = await apiCall('history?limit=1');
      hideLoading(loading);
      
      if (data.history && data.history.length > 0) {
        // User has history - show their last result
        const last = data.history[0];
        currentWord = { word: last.word, id: last.card_id };
        timeElapsed = last.time_taken;
        
        // Set mode variables from API response
        currentMode = data.mode || cfg.mode || 1;
        totalWords = data.total_words || cfg.wordCount || 1;
        completedWords = data.completed || 0;
        
        showResults(last.association_1, last.association_2, last.association_3, last.ai_summary);
      } else {
        // New user - show welcome screen
        showWelcome();
      }
    } catch (err) {
      hideLoading(loading);
      // If error checking history, just show welcome
      showWelcome();
    }
  }

  function showWelcome() {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card wa-welcome');

    const title = el('h1', 'wa-title', 'Word Association');
    const subtitle = el('p', 'wa-subtitle', 'Discover what words mean to you');
    
    const mode = cfg.mode || 1;
    const wordCount = cfg.wordCount || 1;
    
    const instructions = el('div', 'wa-instructions');
    
    if (mode === 2) {
      // Mode 2: Fixed word set
      instructions.innerHTML = `
        <h3>How it works:</h3>
        <ol>
          <li>You'll complete <strong>${wordCount} word association${wordCount > 1 ? 's' : ''}</strong></li>
          <li>For each word, you have <strong>${TIMER_DURATION} seconds</strong> to type 3 associations</li>
          <li>Write the first things that come to mind - there are no wrong answers</li>
          <li>Our AI will analyze your responses and provide insights</li>
        </ol>
        <p style="margin-top: 20px; color: #666; font-size: 14px;">
          💡 <em>Tip: Don't overthink it! Your immediate reactions are the most revealing.</em>
        </p>
      `;
    } else {
      // Mode 1: Random unlimited
      instructions.innerHTML = `
        <h3>How it works:</h3>
        <ol>
          <li>You'll see a word appear on screen</li>
          <li>You have <strong>${TIMER_DURATION} seconds</strong> to type 3 associations</li>
          <li>Write the first things that come to mind - there are no wrong answers</li>
          <li>Our AI will analyze your responses and provide insights</li>
        </ol>
        <p style="margin-top: 20px; color: #666; font-size: 14px;">
          💡 <em>Tip: Don't overthink it! Your immediate reactions are the most revealing.</em>
        </p>
      `;
    }

    const startBtn = el('button', 'wa-btn wa-btn-large', 'Start');
    startBtn.onclick = loadWord;

    const historyBtn = el('button', 'wa-btn wa-secondary', 'View Past Associations');
    historyBtn.onclick = showHistory;

    const btnGroup = el('div', 'wa-btn-group');
    btnGroup.appendChild(startBtn);
    btnGroup.appendChild(historyBtn);

    card.appendChild(title);
    card.appendChild(subtitle);
    card.appendChild(instructions);
    card.appendChild(btnGroup);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  async function loadWord() {
    const loading = showLoading('Finding a word for you...');

    try {
      const data = await apiCall(`word?category=${encodeURIComponent(cfg.category || '')}`);
      currentWord = data.word;
      currentMode = data.mode || 1;           // ← NEW LINE
      totalWords = data.total_words || 0;      // ← NEW LINE
      completedWords = data.completed || 0;    // ← NEW LINE
      hideLoading(loading);
      startAssociation();
    } catch (err) {
      hideLoading(loading);
      
      // Check if all words complete in Mode 2        // ← NEW SECTION
      if (err.message && err.message.includes('All words completed')) {
        showAllComplete();
      } else {
        showError('Failed to load word. Please try again.');
      }
    }
  }

  function startAssociation() {
    timeRemaining = TIMER_DURATION;
    startTime = Date.now();
    
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card wa-active');

    // Timer display
    const timerDisplay = el('div', 'wa-timer');
    timerDisplay.id = 'wa-timer-display';
    timerDisplay.textContent = timeRemaining;
    card.appendChild(timerDisplay);

    // Progress bar
    const progressBar = el('div', 'wa-progress-bar');
    const progressFill = el('div', 'wa-progress-fill');
    progressFill.id = 'wa-progress-fill';
    progressFill.style.width = '100%';
    progressBar.appendChild(progressFill);
    card.appendChild(progressBar);

    // The word
    const wordDisplay = el('div', 'wa-word-display');
    const wordLabel = el('div', 'wa-word-label', 'Your word:');
    const wordText = el('div', 'wa-word', currentWord.word);
    wordDisplay.appendChild(wordLabel);
    wordDisplay.appendChild(wordText);
    card.appendChild(wordDisplay);

    // Category badge
    if (currentWord.category) {
      const categoryBadge = el('div', 'wa-category', currentWord.category);
      card.appendChild(categoryBadge);
    }

    // Instructions
    const prompt = el('div', 'wa-prompt', 
      'What does this word make you think of? Type 3 things that come to mind:');
    card.appendChild(prompt);

    // Input fields
    const inputsContainer = el('div', 'wa-inputs');
    
    for (let i = 1; i <= 3; i++) {
      const inputGroup = el('div', 'wa-input-group');
      const label = el('label', 'wa-input-label', `${i}.`);
      const input = el('input', 'wa-input');
      input.type = 'text';
      input.id = `wa-input-${i}`;
      input.placeholder = 'Type your association...';
      input.maxLength = 200;
      
      // Auto-focus next field
      if (i < 3) {
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById(`wa-input-${i + 1}`).focus();
          }
        });
      } else {
        // Submit on Enter in last field
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            submitAssociations();
          }
        });
      }
      
      inputGroup.appendChild(label);
      inputGroup.appendChild(input);
      inputsContainer.appendChild(inputGroup);
    }
    
    card.appendChild(inputsContainer);

    // Submit button
    const submitBtn = el('button', 'wa-btn wa-submit', 'Submit');
    submitBtn.id = 'wa-submit-btn';
    submitBtn.onclick = submitAssociations;
    card.appendChild(submitBtn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);

    // Focus first input
    document.getElementById('wa-input-1').focus();

    // Start countdown timer
    startTimer();
  }

  function startTimer() {
    const timerDisplay = document.getElementById('wa-timer-display');
    const progressFill = document.getElementById('wa-progress-fill');
    
    timer = setInterval(() => {
      timeRemaining--;
      
      if (timerDisplay) {
        timerDisplay.textContent = timeRemaining;
        
        // Change color as time runs out
        if (timeRemaining <= 5) {
          timerDisplay.classList.add('wa-timer-warning');
        }
        if (timeRemaining <= 10) {
          timerDisplay.classList.add('wa-timer-low');
        }
      }
      
      if (progressFill) {
        const percentage = (timeRemaining / TIMER_DURATION) * 100;
        progressFill.style.width = percentage + '%';
      }
      
      if (timeRemaining <= 0) {
        clearInterval(timer);
        submitAssociations();
      }
    }, 1000);
  }

  async function submitAssociations() {
    clearInterval(timer);
    
    const input1 = document.getElementById('wa-input-1');
    const input2 = document.getElementById('wa-input-2');
    const input3 = document.getElementById('wa-input-3');
    
    if (!input1 || !input2 || !input3) return;
    
    const assoc1 = input1.value.trim();
    const assoc2 = input2.value.trim();
    const assoc3 = input3.value.trim();
    
    // Check if at least one association provided
    if (!assoc1 && !assoc2 && !assoc3) {
      alert('Please provide at least one association before submitting.');
      return;
    }
    
    timeElapsed = Math.ceil((Date.now() - startTime) / 1000);
    
    const loading = showLoading('Analyzing your associations...');
    
    try {
      const data = await apiCall('save', 'POST', {
        card_id: currentWord.id,
        word: currentWord.word,
        association_1: assoc1 || '(no response)',
        association_2: assoc2 || '(no response)',
        association_3: assoc3 || '(no response)',
        time_taken: timeElapsed
      });
      
      // Update mode variables from response
      if (data.mode) {
        currentMode = data.mode;
        totalWords = data.total_words || 0;
        completedWords = data.completed || 0;
      }
      
      hideLoading(loading);
      showResults(assoc1, assoc2, assoc3, data.summary);
    } catch (err) {
      hideLoading(loading);
      showError('Failed to save associations. Please try again.');
    }
  }

  function showResults(assoc1, assoc2, assoc3, summary) {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card wa-results');

    const title = el('h2', 'wa-title', 'Your Insights');
    card.appendChild(title);
    
    // Progress indicator for Mode 2
    if (currentMode === 2 && totalWords > 1) {
      const progress = el('div', 'wa-progress-indicator');
      progress.innerHTML = `<p style="text-align: center; color: #666; font-size: 14px; margin: -10px 0 20px;">Question ${completedWords + 1} of ${totalWords}</p>`;
      card.appendChild(progress);
    }

    // Word reminder
    const wordReminder = el('div', 'wa-word-reminder');
    wordReminder.innerHTML = `Your word was: <strong>${currentWord.word}</strong>`;
    card.appendChild(wordReminder);

    // Associations display
    const assocDisplay = el('div', 'wa-associations-display');
    const assocTitle = el('h3', 'wa-section-title', 'Your Associations:');
    assocDisplay.appendChild(assocTitle);
    
    const assocList = el('div', 'wa-assoc-list');
    
    [assoc1, assoc2, assoc3].forEach((assoc, i) => {
      if (assoc && assoc !== '(no response)') {
        const item = el('div', 'wa-assoc-item');
        const number = el('span', 'wa-assoc-number', `${i + 1}.`);
        const text = el('span', 'wa-assoc-text', assoc);
        item.appendChild(number);
        item.appendChild(text);
        assocList.appendChild(item);
      }
    });
    
    assocDisplay.appendChild(assocList);
    card.appendChild(assocDisplay);

    // Time display
    const timeDisplay = el('div', 'wa-time-display');
    timeDisplay.innerHTML = `⏱️ Completed in <strong>${timeElapsed} seconds</strong>`;
    card.appendChild(timeDisplay);

    // AI Summary
    const summaryBox = el('div', 'wa-summary-box');
    const summaryTitle = el('h3', 'wa-section-title', '🤖 AI Insight:');
    const summaryText = el('div', 'wa-summary-text', summary);
    summaryBox.appendChild(summaryTitle);
    summaryBox.appendChild(summaryText);
    card.appendChild(summaryBox);

    // Action buttons
    const btnGroup = el('div', 'wa-btn-group');
    
    // Mode 1: Always show both buttons
    // Mode 2: Conditional based on progress
    if (currentMode === 1) {
      // Mode 1: Unlimited - always show both buttons
      const nextBtn = el('button', 'wa-btn', 'Next Word');
      nextBtn.onclick = loadWord;
      btnGroup.appendChild(nextBtn);
      
      const historyBtn = el('button', 'wa-btn wa-secondary', 'View History');
      historyBtn.onclick = showHistory;
      btnGroup.appendChild(historyBtn);
      
    } else if (currentMode === 2) {
      // Mode 2: Fixed set - conditional buttons
      const hasMore = (completedWords + 1) < totalWords;
      const hasMultiple = totalWords >= 2;
      const completedMultiple = (completedWords + 1) >= 2;
      
      // Show Next Word button if: word count is 2+ AND not all complete
      if (hasMultiple && hasMore) {
        const nextBtn = el('button', 'wa-btn', 'Next Word');
        nextBtn.onclick = loadWord;
        btnGroup.appendChild(nextBtn);
      }
      
      // Show View History button if: word count is 2-5 AND completed 2+ associations
      if (hasMultiple && completedMultiple) {
        const historyBtn = el('button', 'wa-btn wa-secondary', 'View History');
        historyBtn.onclick = showHistory;
        btnGroup.appendChild(historyBtn);
      }
      
      // If all complete, show completion message
      if (!hasMore) {
        const completeMsg = el('div', 'wa-complete-message');
        completeMsg.innerHTML = '<h3 style="color: #00a32a; text-align: center; margin-top: 20px;">🎉 All Complete!</h3><p style="text-align: center; color: #666;">You\'ve completed all ' + totalWords + ' word associations.</p>';
        card.appendChild(completeMsg);
        
        // Only show View History button if they completed multiple words
        if (totalWords >= 2) {
          const historyBtn = el('button', 'wa-btn', 'View Your History');
          historyBtn.onclick = showHistory;
          historyBtn.style.cssText = 'display: block; margin: 20px auto 0;';
          card.appendChild(historyBtn);
        }
      }
    }
    
    card.appendChild(btnGroup);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  async function showHistory() {
    const loading = showLoading('Loading your history...');

    try {
      const data = await apiCall('history?limit=20');
      hideLoading(loading);
      renderHistory(data.history || []);
    } catch (err) {
      hideLoading(loading);
      showError('Failed to load history.');
    }
  }

  function renderHistory(history) {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');

    const title = el('h2', 'wa-title', 'Your Association History');
    card.appendChild(title);

    if (history.length === 0) {
      const empty = el('p', 'wa-empty', 'You haven\'t completed any word associations yet. Start your first one!');
      card.appendChild(empty);
      
      const startBtn = el('button', 'wa-btn', 'Start Now');
      startBtn.onclick = loadWord;
      card.appendChild(startBtn);
    } else {
      const historyList = el('div', 'wa-history-list');
      
      history.forEach(item => {
        const historyCard = el('div', 'wa-history-card');
        
        const header = el('div', 'wa-history-header');
        const wordEl = el('div', 'wa-history-word', item.word);
        const date = new Date(item.created_at);
        const dateEl = el('div', 'wa-history-date', date.toLocaleDateString());
        header.appendChild(wordEl);
        header.appendChild(dateEl);
        
        const associations = el('div', 'wa-history-associations');
        associations.innerHTML = `
          <div class="wa-history-assoc">1. ${item.association_1}</div>
          <div class="wa-history-assoc">2. ${item.association_2}</div>
          <div class="wa-history-assoc">3. ${item.association_3}</div>
        `;
        
        const summary = el('div', 'wa-history-summary');
        const summaryLabel = el('div', 'wa-history-summary-label', 'AI Insight:');
        const summaryText = el('div', 'wa-history-summary-text', item.ai_summary || 'No summary generated');
        summary.appendChild(summaryLabel);
        summary.appendChild(summaryText);
        
        historyCard.appendChild(header);
        historyCard.appendChild(associations);
        historyCard.appendChild(summary);
        
        historyList.appendChild(historyCard);
      });
      
      card.appendChild(historyList);
    }

    const backBtn = el('button', 'wa-btn wa-secondary', 'Back');
    backBtn.onclick = () => {
    // Go back to most recent result if exists, otherwise welcome
    if (history.length > 0) {
      const last = history[0];
      currentWord = { word: last.word, id: last.card_id };
      timeElapsed = last.time_taken;
      showResults(last.association_1, last.association_2, last.association_3, last.ai_summary);
    } else {
      showWelcome();
    }
    };

    card.appendChild(backBtn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

 function showAllComplete() {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');
    
    const title = el('h1', 'wa-title', '🎉 Congratulations!');
    card.appendChild(title);
    
    const message = el('div', 'wa-complete-message');
    message.innerHTML = '<p style="font-size: 18px; text-align: center; line-height: 1.6;">You\'ve completed all word associations!</p>';
    message.style.cssText = 'text-align: center; padding: 20px; background: #d4edda; border-radius: 8px; margin: 20px 0;';
    card.appendChild(message);
    
    const historyBtn = el('button', 'wa-btn', 'View Your History');
    historyBtn.onclick = showHistory;
    historyBtn.style.cssText = 'display: block; margin: 0 auto;';
    card.appendChild(historyBtn);
    
    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  function showError(message) {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');
    
    const errorBox = el('div', 'wa-error', message);
    card.appendChild(errorBox);
    
    const retryBtn = el('button', 'wa-btn', 'Try Again');
    retryBtn.onclick = showWelcome;
    card.appendChild(retryBtn);
    
    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  

  // ==================== INITIALIZATION ====================
  init();

})();