Minimal JS changes — strip hardcoded light-theme inline styles so the
gamer CSS classes fully control appearance. All logic stays identical.

──────────────────────────────────────────────────────────────────────
1. showWelcome() — tip paragraph  (hardcoded colour)
──────────────────────────────────────────────────────────────────────
FIND (both Mode 1 and Mode 2 instruction blocks):
        <p style="margin-top: 20px; color: #666; font-size: 14px;">
          💡 <em>Tip: Don't overthink it! Your immediate reactions are the most revealing.</em>
        </p>

REPLACE WITH:
        <p style="margin-top: 20px; font-size: 14px;">
          <em>Tip: Don't overthink it! Your immediate reactions are the most revealing.</em>
        </p>


──────────────────────────────────────────────────────────────────────
2. showResults() — progress indicator paragraph  (hardcoded colour)
──────────────────────────────────────────────────────────────────────
FIND:
      progress.innerHTML = `<p style="text-align: center; color: #666; font-size: 14px; margin: -10px 0 20px;">Question ${completedWords} of ${totalWords}</p>`;

REPLACE WITH:
      progress.innerHTML = `<p style="text-align: center; margin: -10px 0 20px;">Question ${completedWords} of ${totalWords}</p>`;


──────────────────────────────────────────────────────────────────────
3. showResults() — "All Complete" inline message  (hardcoded colours)
──────────────────────────────────────────────────────────────────────
FIND:
        completeMsg.innerHTML = '<h3 style="color: #00a32a; text-align: center; margin-top: 20px;">🎉 All Complete!</h3><p style="text-align: center; color: #666;">You\'ve completed all ' + totalWords + ' word associations.</p>';

REPLACE WITH:
        completeMsg.innerHTML = '<h3>All Complete!</h3><p>You\'ve completed all ' + totalWords + ' word associations.</p>';


──────────────────────────────────────────────────────────────────────
4. showResults() — wa-complete-message class  (add class, remove inline)
──────────────────────────────────────────────────────────────────────
FIND:
        const completeMsg = el('div', 'wa-complete-message');

(No change needed — the class is already wa-complete-message which the
 new CSS fully styles. Just ensure no cssText is set on it below.)

FIND (immediately after the completeMsg creation):
        card.appendChild(completeMsg);
        
        // Show single View History button
        if (totalWords >= 2) {
          const soloHistoryBtn = el('button', 'wa-btn', 'View Your History');
          soloHistoryBtn.onclick = showHistory;
          soloHistoryBtn.style.cssText = 'display: block; margin: 20px auto 0;';
          card.appendChild(soloHistoryBtn);
        }

REPLACE WITH:
        card.appendChild(completeMsg);
        
        if (totalWords >= 2) {
          const soloHistoryBtn = el('button', 'wa-btn', 'View Your History');
          soloHistoryBtn.onclick = showHistory;
          soloHistoryBtn.style.cssText = 'display: block; margin: 20px auto 0;';
          card.appendChild(soloHistoryBtn);
        }


──────────────────────────────────────────────────────────────────────
5. showAllComplete() — inline background style  (hardcoded green)
──────────────────────────────────────────────────────────────────────
FIND:
    message.style.cssText = 'text-align: center; padding: 20px; background: #d4edda; border-radius: 8px; margin: 20px 0;';

REPLACE WITH:
    message.style.cssText = '';


──────────────────────────────────────────────────────────────────────
6. showAllComplete() — historyBtn inline style  (keep, cosmetically fine)
──────────────────────────────────────────────────────────────────────
No change needed.


──────────────────────────────────────────────────────────────────────
7. Timer colour transitions — update JS colour values to match gamer theme
──────────────────────────────────────────────────────────────────────
The timer colour changes are handled entirely by the CSS classes
wa-timer-low and wa-timer-warning — no JS colour values to update.
No change needed.