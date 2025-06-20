let duration = 8 * 60; // 8 minutes for single room
const timerDisplay = document.getElementById('timer');

function startTimer() {
  const interval = setInterval(() => {
    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;
    timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    // Change color when time is running out
    if (duration <= 60) {
      timerDisplay.style.background = 'linear-gradient(45deg, #e74c3c, #c0392b)';
      timerDisplay.style.animation = 'pulse 1s infinite';
    } else if (duration <= 180) {
      timerDisplay.style.background = 'linear-gradient(45deg, #f39c12, #e67e22)';
    }
    
    if (--duration < 0) {
      clearInterval(interval);
      window.location.href = 'lose.php';
    }
  }, 1000);
}

// Function to update progress bar
function updateProgress() {
  const puzzles = ['clock', 'drawer', 'computer', 'painting', 'safe', 'bookshelf'];
  let solvedCount = 0;
  
  // Count solved puzzles (this is a simplified version - in production you'd check session data)
  puzzles.forEach(puzzle => {
    const element = document.getElementById(puzzle + '-hotspot');
    if (element && element.classList.contains('solved')) {
      solvedCount++;
    }
  });
  
  const progressBar = document.getElementById('progress');
  const progressText = document.getElementById('progress-text');
  
  if (progressBar) {
    const percentage = (solvedCount / 6) * 100;
    progressBar.style.width = percentage + '%';
  }
  
  if (progressText) {
    progressText.textContent = `${solvedCount}/6 Puzzels opgelost`;
  }
  
  // Show door if all puzzles solved
  if (solvedCount === 6) {
    const doorButton = document.getElementById('door-hotspot');
    if (doorButton) {
      doorButton.style.display = 'block';
      doorButton.style.animation = 'pulse 2s infinite';
    }
  }
}

// Hint system
function showHint(puzzleId) {
  const hintDiv = document.getElementById(puzzleId + '-hint');
  if (hintDiv.style.display === 'none') {
    hintDiv.style.display = 'block';
    
    // Track hint usage via AJAX
    fetch('track_hint.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'puzzle=' + puzzleId
    });
    
    // Deduct 30 seconds from timer
    duration -= 30;
    
    // Show warning
    const warning = document.createElement('div');
    warning.className = 'hint-warning';
    warning.textContent = '-30 seconden voor hint!';
    hintDiv.parentNode.appendChild(warning);
    
    setTimeout(() => {
      warning.remove();
    }, 2000);
  }
}

// Start timer when page loads
document.addEventListener('DOMContentLoaded', function() {
  if (timerDisplay) {
    startTimer();
  }
  
  // Auto-hide feedback messages
  const feedbackMsg = document.querySelector('.feedback-message');
  if (feedbackMsg) {
    setTimeout(() => {
      feedbackMsg.style.opacity = '0';
      setTimeout(() => {
        feedbackMsg.style.display = 'none';
      }, 500);
    }, 4000);
  }
  
  // Setup hotspot clicks
  document.querySelectorAll('.hotspot').forEach(function(button) {
    button.addEventListener('click', function() {
      const popupId = button.getAttribute('data-popup');
      openPopup(popupId);
    });
  });
  
  // Add solved class based on session (you'd need to pass this from PHP)
  const solvedPuzzles = document.body.getAttribute('data-solved');
  if (solvedPuzzles) {
    solvedPuzzles.split(',').forEach(puzzle => {
      const element = document.getElementById(puzzle + '-hotspot');
      if (element) {
        element.classList.add('solved');
      }
    });
  }
});

// Function to open a popup
function openPopup(id) {
  document.getElementById(id).style.display = 'block';
  // Add blur to background
  document.querySelector('.room-container').style.filter = 'blur(3px)';
}

// Function to close a popup
function closePopup(id) {
  document.getElementById(id).style.display = 'none';
  // Remove blur
  document.querySelector('.room-container').style.filter = 'none';
}

// Close popup when clicking outside
document.querySelectorAll('.popup').forEach(popup => {
  popup.addEventListener('click', (e) => {
    if (e.target === popup) {
      popup.style.display = 'none';
      document.querySelector('.room-container').style.filter = 'none';
    }
  });
});

// Escape key to close popups
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.popup').forEach(popup => {
      popup.style.display = 'none';
    });
    document.querySelector('.room-container').style.filter = 'none';
  }
});

// Add celebration effect when puzzle is solved
function celebrateSolution(puzzleId) {
  const button = document.getElementById(puzzleId + '-hotspot');
  if (button) {
    button.classList.add('solved');
    button.style.animation = 'celebrate 0.5s ease';
    
    // Create confetti effect
    for (let i = 0; i < 20; i++) {
      const confetti = document.createElement('div');
      confetti.className = 'confetti';
      confetti.style.left = button.offsetLeft + 'px';
      confetti.style.top = button.offsetTop + 'px';
      confetti.style.backgroundColor = ['#f39c12', '#e67e22', '#2ecc71', '#3498db'][Math.floor(Math.random() * 4)];
      document.body.appendChild(confetti);
      
      setTimeout(() => confetti.remove(), 1000);
    }
  }
  
  updateProgress();
}