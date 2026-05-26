(function () {
  console.log('MFSD_WA_CFG', window.MFSD_WA_CFG);
  const cfg = window.MFSD_WA_CFG || {};
  const root = document.getElementById("mfsd-word-assoc-root");
  if (!root) return;

  const TIMER_DURATION = cfg.timer || 20;
  const isParentView   = cfg.role === 'parent' && cfg.studentId > 0;

  let currentMode    = 1;
  let totalWords     = 0;
  let completedWords = 0;
  let currentWord    = null;
  let timer          = null;
  let timeRemaining  = TIMER_DURATION;
  let timeElapsed    = 0;
  let startTime      = null;

  const el = (t, c, txt) => {
    const n = document.createElement(t);
    if (c) n.className = c;
    if (txt !== undefined) n.textContent = txt;
    return n;
  };

  // ==================== UTILITY ====================

  function showLoading(message = 'Loading...') {
    const overlay = el('div', 'wa-loading-overlay');
    const spinner = el('div', 'wa-spinner');
    const text    = el('div', 'wa-loading-text', message);
    overlay.appendChild(spinner);
    overlay.appendChild(text);
    document.body.appendChild(overlay);
    return overlay;
  }

  function hideLoading(overlay) {
    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
  }

  async function apiCall(endpoint, method = 'GET', body = null) {
    const options = {
      method,
      headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin'
    };
    if (body) options.body = JSON.stringify(body);
    try {
      const res = await fetch(cfg.restUrl + endpoint, options);
      if (!res.ok) { const e = new Error('API request failed'); e.status = res.status; throw e; }
      return await res.json();
    } catch (err) {
      console.error('API Error:', err);
      throw err;
    }
  }

  function formatSummaryForDisplay(text) {
    if (!text) return 'No summary generated';
    text = text.replace(/:\*+/g, ':**');
    text = text.replace(/\*+:/g, '**:');
    text = text.replace(/\*{4,}([^*]+)\*{4,}/g, '<strong>$1</strong>');
    text = text.replace(/\*\*\*([^*]+)\*\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    text = text.replace(/\*+/g, '');
    text = text.replace(/\n/g, '<br>');
    return text;
  }

  // ==================== PARENT VIEW HELPERS ====================

  const parentSummaryCache = new Map();

  function appendChatbot(targetEl) {
    if (!cfg.hasChatbot) return;
    const container = document.getElementById('mfsd-wa-chatbot-container');
    if (!container) return;
    container.style.display = 'block';
    targetEl.appendChild(container);
  }

  function renderTabBar(labels, activeIndex, onSwitch) {
    const bar = el('div', 'wa-tab-bar');
    labels.forEach((label, i) => {
      const btn = el('button', 'wa-tab-btn' + (i === activeIndex ? ' wa-tab-active' : ''), label);
      btn.onclick = () => {
        bar.querySelectorAll('.wa-tab-btn').forEach((b, j) => {
          b.classList.toggle('wa-tab-active', j === i);
        });
        onSwitch(i);
      };
      bar.appendChild(btn);
    });
    return bar;
  }

  async function renderParentTab(associationId, container) {
    container.innerHTML = '';
    const section = el('div', 'wa-parent-summary-section');
    section.appendChild(el('h3', 'wa-section-title wa-parent-label', 'Parent Summary'));
    const text = el('div', 'wa-parent-summary-text', 'Loading…');
    section.appendChild(text);
    container.appendChild(section);

    if (parentSummaryCache.has(associationId)) {
      text.innerHTML = formatSummaryForDisplay(parentSummaryCache.get(associationId));
      return;
    }
    try {
      const data = await apiCall(
        `parent-summary?association_id=${associationId}&student_id=${cfg.studentId}`
      );
      parentSummaryCache.set(associationId, data.summary || '');
      text.innerHTML = formatSummaryForDisplay(data.summary || '');
    } catch {
      text.textContent = 'Unable to load parent summary. Please try again later.';
    }
  }

  async function renderParentHistoryTab(history, container) {
    if (history.length === 0) {
      container.appendChild(el('p', 'wa-empty', 'No completed word associations yet.'));
      return;
    }

    const list = el('div', 'wa-parent-history-list');

    history.forEach((item, i) => {
      const card = el('div', 'wa-parent-history-card');

      const header = el('div', 'wa-history-header');
      header.appendChild(el('div', 'wa-history-word', item.word));
      header.appendChild(el('div', 'wa-history-date', new Date(item.created_at).toLocaleDateString()));
      card.appendChild(header);

      const assocs = el('div', 'wa-history-associations');
      assocs.innerHTML = `
        <div class="wa-history-assoc">1. ${item.association_1}</div>
        <div class="wa-history-assoc">2. ${item.association_2}</div>
        <div class="wa-history-assoc">3. ${item.association_3}</div>`;
      card.appendChild(assocs);

      const summaryBox  = el('div', 'wa-parent-summary-box');
      const summaryText = el('div', 'wa-parent-summary-text', 'Loading…');
      summaryBox.appendChild(el('div', 'wa-parent-summary-label', 'Parent Summary:'));
      summaryBox.appendChild(summaryText);
      card.appendChild(summaryBox);

      list.appendChild(card);

      // Staggered lazy load — 150ms between cards
      setTimeout(async () => {
        if (parentSummaryCache.has(item.id)) {
          summaryText.innerHTML = formatSummaryForDisplay(parentSummaryCache.get(item.id));
          return;
        }
        try {
          const data = await apiCall(
            `parent-summary?association_id=${item.id}&student_id=${cfg.studentId}`
          );
          parentSummaryCache.set(item.id, data.summary || '');
          summaryText.innerHTML = formatSummaryForDisplay(data.summary || '');
        } catch {
          summaryText.textContent = 'Summary unavailable.';
        }
      }, i * 150);
    });

    container.appendChild(list);
  }

  // ==================== MAIN FLOW ====================

  async function init() {
    const loading = showLoading('Loading...');
    try {
      if (isParentView) {
        // Parent-portal view: fetch the student's history read-only.
        const data = await apiCall(`student-history?student_id=${cfg.studentId}&limit=20`);
        hideLoading(loading);
        if (data.history && data.history.length > 0) {
          renderHistory(data.history, true);
        } else {
          showParentNoData();
        }
      } else {
        const data = await apiCall('history?limit=1');
        hideLoading(loading);
        if (data.history && data.history.length > 0) {
          const last = data.history[0];
          currentWord    = { word: last.word, id: last.card_id };
          timeElapsed    = last.time_taken;
          currentMode    = data.mode || cfg.mode || 1;
          totalWords     = data.total_words || cfg.wordCount || 1;
          completedWords = data.completed || 0;
          showResults(last.association_1, last.association_2, last.association_3, last.ai_summary, last.id || 0);
        } else {
          showWelcome(false);
        }
      }
    } catch (err) {
      hideLoading(loading);
      // 403 means this user isn't a linked parent — fall back to the student start screen.
      if (isParentView && err.status !== 403) {
        showParentNoData();
      } else {
        showWelcome(false);
      }
    }
  }

  function showParentNoData() {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');
    card.appendChild(el('h2', 'wa-title', 'Word Association'));
    card.appendChild(el('p', 'wa-empty', 'No completed word associations yet for this student.'));
    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  function showWelcome(hasHistory) {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card wa-welcome');

    const title    = el('h1', 'wa-title', 'Word Association');
    const subtitle = el('p',  'wa-subtitle', 'Discover what words mean to you');

    const mode      = cfg.mode || 1;
    const wordCount = cfg.wordCount || 1;
    const instructions = el('div', 'wa-instructions');

    if (mode == 2) {
      instructions.innerHTML = `
        <h3>How it works:</h3>
        <ol>
          <li>You'll complete <strong>${wordCount} word association${wordCount > 1 ? 's' : ''}</strong></li>
          <li>For each word, you have <strong>${TIMER_DURATION} seconds</strong> to type 3 associations</li>
          <li>Write the first things that come to mind - there are no wrong answers</li>
          <li>Our AI will analyze your responses and provide insights</li>
        </ol>
        <p style="margin-top:20px;font-size:14px;">
          <em>Tip: Don't overthink it! Your immediate reactions are the most revealing.</em>
        </p>`;
    } else {
      instructions.innerHTML = `
        <h3>How it works:</h3>
        <ol>
          <li>You'll see a word appear on screen</li>
          <li>You have <strong>${TIMER_DURATION} seconds</strong> to type 3 associations</li>
          <li>Write the first things that come to mind - there are no wrong answers</li>
          <li>Our AI will analyze your responses and provide insights</li>
        </ol>
        <p style="margin-top:20px;font-size:14px;">
          <em>Tip: Don't overthink it! Your immediate reactions are the most revealing.</em>
        </p>`;
    }

    const startBtn = el('button', 'wa-btn wa-btn-large', 'Start');
    startBtn.onclick = loadWord;

    const btnGroup = el('div', 'wa-btn-group');
    btnGroup.appendChild(startBtn);

    if (hasHistory) {
      const historyBtn = el('button', 'wa-btn wa-secondary', 'View Past Associations');
      historyBtn.onclick = showHistory;
      btnGroup.appendChild(historyBtn);
    }

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
      currentWord    = data.word;
      currentMode    = data.mode || 1;
      totalWords     = data.total_words || 0;
      completedWords = data.completed || 0;
      hideLoading(loading);
      startAssociation();
    } catch (err) {
      hideLoading(loading);
      if (err.message && err.message.includes('All words completed')) {
        showAllComplete();
      } else {
        showError('Failed to load word. Please try again.');
      }
    }
  }

  function startAssociation() {
    timeRemaining = TIMER_DURATION;
    startTime     = Date.now();

    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card wa-active');

    const timerDisplay = el('div', 'wa-timer');
    timerDisplay.id = 'wa-timer-display';
    timerDisplay.textContent = timeRemaining;
    card.appendChild(timerDisplay);

    const progressBar  = el('div', 'wa-progress-bar');
    const progressFill = el('div', 'wa-progress-fill');
    progressFill.id = 'wa-progress-fill';
    progressFill.style.width = '100%';
    progressBar.appendChild(progressFill);
    card.appendChild(progressBar);

    const wordDisplay = el('div', 'wa-word-display');
    const wordLabel   = el('div', 'wa-word-label', 'Your word:');
    const wordText    = el('div', 'wa-word', currentWord.word);
    wordDisplay.appendChild(wordLabel);
    wordDisplay.appendChild(wordText);
    card.appendChild(wordDisplay);

    if (currentWord.category) {
      card.appendChild(el('div', 'wa-category', currentWord.category));
    }

    card.appendChild(el('div', 'wa-prompt', 'What does this word make you think of? Type 3 things that come to mind:'));

    const inputsContainer = el('div', 'wa-inputs');
    for (let i = 1; i <= 3; i++) {
      const inputGroup = el('div', 'wa-input-group');
      const label      = el('label', 'wa-input-label', `${i}.`);
      const input      = el('input', 'wa-input');
      input.type       = 'text';
      input.id         = `wa-input-${i}`;
      input.placeholder = 'Type your association...';
      input.maxLength  = 200;

      if (i < 3) {
        input.addEventListener('keydown', e => {
          if (e.key === 'Enter') { e.preventDefault(); document.getElementById(`wa-input-${i + 1}`).focus(); }
        });
      } else {
        input.addEventListener('keydown', e => {
          if (e.key === 'Enter') { e.preventDefault(); submitAssociations(); }
        });
      }

      inputGroup.appendChild(label);
      inputGroup.appendChild(input);
      inputsContainer.appendChild(inputGroup);
    }
    card.appendChild(inputsContainer);

    const submitBtn = el('button', 'wa-btn wa-submit', 'Submit');
    submitBtn.id = 'wa-submit-btn';
    submitBtn.onclick = submitAssociations;
    card.appendChild(submitBtn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);

    document.getElementById('wa-input-1').focus();
    startTimer();
  }

  function startTimer() {
    const timerDisplay = document.getElementById('wa-timer-display');
    const progressFill = document.getElementById('wa-progress-fill');

    timer = setInterval(() => {
      timeRemaining--;

      if (timerDisplay) {
        timerDisplay.textContent = timeRemaining;
        if (timeRemaining <= 5) {
          timerDisplay.classList.add('wa-timer-warning');
          timerDisplay.classList.remove('wa-timer-low');
        } else if (timeRemaining <= 10) {
          timerDisplay.classList.add('wa-timer-low');
        }
      }

      if (progressFill) {
        progressFill.style.width = ((timeRemaining / TIMER_DURATION) * 100) + '%';
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

    if (!assoc1 && !assoc2 && !assoc3) {
      alert('Please provide at least one association before submitting.');
      return;
    }

    timeElapsed = Math.ceil((Date.now() - startTime) / 1000);
    const loading = showLoading('Analyzing your associations...');

    try {
      const data = await apiCall('save', 'POST', {
        card_id:       currentWord.id,
        word:          currentWord.word,
        association_1: assoc1 || '(no response)',
        association_2: assoc2 || '(no response)',
        association_3: assoc3 || '(no response)',
        time_taken:    timeElapsed
      });

      if (data.mode) {
        currentMode    = data.mode;
        totalWords     = data.total_words || 0;
        completedWords = data.completed   || 0;
      }

      hideLoading(loading);
      const assocId = data.association_id || 0;
      showResults(assoc1, assoc2, assoc3, data.summary, assocId);
    } catch (err) {
      hideLoading(loading);
      showError('Failed to save associations. Please try again.');
    }
  }

  function showResults(assoc1, assoc2, assoc3, summary, associationId) {
    associationId = associationId || 0;
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card wa-results');

    card.appendChild(el('h2', 'wa-title', 'Your Insights'));

    if (currentMode == 2 && totalWords > 1) {
      const progress = el('div', 'wa-progress-indicator');
      progress.innerHTML = `<p style="text-align:center;margin:-10px 0 20px;">Question ${completedWords} of ${totalWords}</p>`;
      card.appendChild(progress);
    }

    const wordReminder = el('div', 'wa-word-reminder');
    wordReminder.innerHTML = `Your word was: <strong>${currentWord.word}</strong>`;
    card.appendChild(wordReminder);

    const assocDisplay = el('div', 'wa-associations-display');
    assocDisplay.appendChild(el('h3', 'wa-section-title', 'Your Associations:'));
    const assocList = el('div', 'wa-assoc-list');

    [assoc1, assoc2, assoc3].forEach((assoc, i) => {
      if (assoc && assoc !== '(no response)') {
        const item   = el('div', 'wa-assoc-item');
        const number = el('span', 'wa-assoc-number', `${i + 1}.`);
        const text   = el('span', 'wa-assoc-text', assoc);
        item.appendChild(number);
        item.appendChild(text);
        assocList.appendChild(item);
      }
    });
    assocDisplay.appendChild(assocList);
    card.appendChild(assocDisplay);

    const timeDisplay = el('div', 'wa-time-display');
    timeDisplay.innerHTML = `Completed in <strong>${timeElapsed} seconds</strong>`;
    card.appendChild(timeDisplay);

    const summaryBox   = el('div', 'wa-summary-box');
    const summaryTitle = el('h3', 'wa-section-title', 'Steve Says:');
    const summaryText  = el('div', 'wa-summary-text');
    summaryText.innerHTML = formatSummaryForDisplay(summary);
    summaryBox.appendChild(summaryTitle);
    summaryBox.appendChild(summaryText);
    card.appendChild(summaryBox);

    const btnGroup = el('div', 'wa-btn-group');

    if (currentMode == 1) {
      const nextBtn = el('button', 'wa-btn', 'Next Word');
      nextBtn.onclick = loadWord;
      btnGroup.appendChild(nextBtn);

      const historyBtn = el('button', 'wa-btn wa-secondary', 'View History');
      historyBtn.onclick = showHistory;
      btnGroup.appendChild(historyBtn);

    } else if (currentMode == 2) {
      const hasMore      = completedWords < totalWords;
      const hasMultiple  = totalWords >= 2;
      const completedMultiple = completedWords >= 2;

      if (hasMultiple && hasMore) {
        const nextBtn = el('button', 'wa-btn', 'Next Word');
        nextBtn.onclick = loadWord;
        btnGroup.appendChild(nextBtn);
      }

      if (hasMultiple && completedMultiple && hasMore) {
        const historyBtn = el('button', 'wa-btn wa-secondary', 'View History');
        historyBtn.onclick = showHistory;
        btnGroup.appendChild(historyBtn);
      }

      if (!hasMore) {
        const completeMsg = el('div', 'wa-complete-message');
        completeMsg.innerHTML = '<h3>All Complete!</h3><p>You\'ve completed all ' + totalWords + ' word associations.</p>';
        card.appendChild(completeMsg);

        if (totalWords >= 2) {
          const soloHistoryBtn = el('button', 'wa-btn', 'View Your History');
          soloHistoryBtn.onclick = showHistory;
          soloHistoryBtn.style.cssText = 'display:block;margin:20px auto 0;';
          card.appendChild(soloHistoryBtn);
        }
      }
    }

    if (btnGroup.children.length > 0) card.appendChild(btnGroup);

    // Navigation buttons — always shown on results (both first completion and re-entry)
    var navDiv = el('div', 'wa-summary-actions');
    if (cfg.badgesUrl) {
      var badgesLink = el('a', 'wa-btn wa-btn--badge', 'See My Badges');
      badgesLink.href = cfg.badgesUrl;
      navDiv.appendChild(badgesLink);
    }
    if (cfg.portalUrl) {
      var progressLink = el('a', 'wa-btn wa-btn--progress', 'View My Progress →');
      progressLink.href = cfg.portalUrl;
      navDiv.appendChild(progressLink);
    }
    card.appendChild(navDiv);

    if (isParentView && associationId) {
      const studentPanel = el('div', 'wa-tab-panel wa-tab-panel--active');
      studentPanel.appendChild(card);
      const parentPanel = el('div', 'wa-tab-panel wa-tab-panel--hidden');
      let parentLoaded = false;

      const tabBar = renderTabBar(['Student View', 'For Parents'], 0, (idx) => {
        studentPanel.classList.toggle('wa-tab-panel--active', idx === 0);
        studentPanel.classList.toggle('wa-tab-panel--hidden', idx !== 0);
        parentPanel.classList.toggle('wa-tab-panel--active', idx === 1);
        parentPanel.classList.toggle('wa-tab-panel--hidden', idx !== 1);
        if (idx === 1 && !parentLoaded) {
          parentLoaded = true;
          renderParentTab(associationId, parentPanel);
        }
      });

      const tabWrap = el('div', 'wa-tab-wrap');
      tabWrap.appendChild(tabBar);
      tabWrap.appendChild(studentPanel);
      tabWrap.appendChild(parentPanel);
      wrap.appendChild(tabWrap);
    } else {
      wrap.appendChild(card);
    }

    appendChatbot(wrap);
    root.replaceChildren(wrap);
  }

  async function showHistory() {
    const loading = showLoading('Loading your history...');
    try {
      const data = await apiCall('history?limit=20');
      hideLoading(loading);
      renderHistory(data.history || [], false);
    } catch (err) {
      hideLoading(loading);
      showError('Failed to load history.');
    }
  }

  // parentView = true: read-only, no Back/Start buttons, title says "Results" not "History"
  function renderHistory(history, parentView) {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');

    card.appendChild(el('h2', 'wa-title', parentView ? 'Word Association Results' : 'Your Association History'));

    if (history.length === 0) {
      card.appendChild(el('p', 'wa-empty', parentView
        ? 'No completed word associations yet for this student.'
        : "You haven't completed any word associations yet. Start your first one!"));
      if (!parentView) {
        const startBtn = el('button', 'wa-btn', 'Start Now');
        startBtn.onclick = loadWord;
        card.appendChild(startBtn);
      }
    } else {
      const historyList = el('div', 'wa-history-list');

      history.forEach(item => {
        const historyCard = el('div', 'wa-history-card');

        const header  = el('div', 'wa-history-header');
        const wordEl  = el('div', 'wa-history-word', item.word);
        const date    = new Date(item.created_at);
        const dateEl  = el('div', 'wa-history-date', date.toLocaleDateString());
        header.appendChild(wordEl);
        header.appendChild(dateEl);

        const associations = el('div', 'wa-history-associations');
        associations.innerHTML = `
          <div class="wa-history-assoc">1. ${item.association_1}</div>
          <div class="wa-history-assoc">2. ${item.association_2}</div>
          <div class="wa-history-assoc">3. ${item.association_3}</div>`;

        const summary      = el('div', 'wa-history-summary');
        const summaryLabel = el('div', 'wa-history-summary-label', 'Steve Says:');
        const summaryText  = el('div', 'wa-history-summary-text');
        summaryText.innerHTML = formatSummaryForDisplay(item.ai_summary);
        summary.appendChild(summaryLabel);
        summary.appendChild(summaryText);

        historyCard.appendChild(header);
        historyCard.appendChild(associations);
        historyCard.appendChild(summary);
        historyList.appendChild(historyCard);
      });

      card.appendChild(historyList);
    }

    if (!parentView) {
      const backBtn = el('button', 'wa-btn wa-secondary', 'Back');
      backBtn.onclick = () => {
        if (history.length > 0) {
          const last = history[0];
          currentWord = { word: last.word, id: last.card_id };
          timeElapsed = last.time_taken;
          showResults(last.association_1, last.association_2, last.association_3, last.ai_summary, last.id || 0);
        } else {
          showWelcome();
        }
      };
      card.appendChild(backBtn);
    }

    if (isParentView) {
      const studentPanel = el('div', 'wa-tab-panel wa-tab-panel--active');
      studentPanel.appendChild(card);
      const parentPanel = el('div', 'wa-tab-panel wa-tab-panel--hidden');
      let parentLoaded = false;

      const tabBar = renderTabBar(['Student View', 'For Parents'], 0, (idx) => {
        studentPanel.classList.toggle('wa-tab-panel--active', idx === 0);
        studentPanel.classList.toggle('wa-tab-panel--hidden', idx !== 0);
        parentPanel.classList.toggle('wa-tab-panel--active', idx === 1);
        parentPanel.classList.toggle('wa-tab-panel--hidden', idx !== 1);
        if (idx === 1 && !parentLoaded) {
          parentLoaded = true;
          renderParentHistoryTab(history, parentPanel);
        }
      });

      const tabWrap = el('div', 'wa-tab-wrap');
      tabWrap.appendChild(tabBar);
      tabWrap.appendChild(studentPanel);
      tabWrap.appendChild(parentPanel);
      wrap.appendChild(tabWrap);
    } else {
      wrap.appendChild(card);
    }

    appendChatbot(wrap);
    root.replaceChildren(wrap);
  }

  function showAllComplete() {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');

    card.appendChild(el('h1', 'wa-title', 'Congratulations!'));

    const message = el('div', 'wa-complete-message');
    message.innerHTML = '<p>You\'ve completed all word associations!</p>';
    card.appendChild(message);

    const historyBtn = el('button', 'wa-btn', 'View Your History');
    historyBtn.onclick = showHistory;
    historyBtn.style.cssText = 'display:block;margin:0 auto;';
    card.appendChild(historyBtn);

    var navDiv2 = el('div', 'wa-summary-actions');
    if (cfg.badgesUrl) {
      var badgesLink2 = el('a', 'wa-btn wa-btn--badge', 'See My Badges');
      badgesLink2.href = cfg.badgesUrl;
      navDiv2.appendChild(badgesLink2);
    }
    if (cfg.portalUrl) {
      var progressLink2 = el('a', 'wa-btn wa-btn--progress', 'View My Progress →');
      progressLink2.href = cfg.portalUrl;
      navDiv2.appendChild(progressLink2);
    }
    card.appendChild(navDiv2);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  function showError(message) {
    const wrap = el('div', 'wa-wrap');
    const card = el('div', 'wa-card');
    card.appendChild(el('div', 'wa-error', message));
    const retryBtn = el('button', 'wa-btn', 'Try Again');
    retryBtn.onclick = showWelcome;
    card.appendChild(retryBtn);
    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  init();
})();