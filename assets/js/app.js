(function () {
  const root = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');

  const setTheme = (mode) => {
    root.dataset.theme = mode;
    const icon = mode === 'dark' ? '🌙' : '☀️';
    const label = mode === 'dark' ? 'Dark' : 'Light';
    if (themeToggle) {
      themeToggle.querySelector('.icon').textContent = icon;
      themeToggle.querySelector('.toggle-label').textContent = label;
    }
    localStorage.setItem('miniBookTheme', mode);
  };

  const current = localStorage.getItem('miniBookTheme');
  setTheme(current === 'light' ? 'light' : 'dark');

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      setTheme(next);
    });
  }

  // AJAX helpers for like / follow buttons
  const ajaxButtons = document.querySelectorAll('[data-action="ajax"]');
  for (const button of ajaxButtons) {
    button.addEventListener('click', async (event) => {
      event.preventDefault();
      const form = button.closest('form');
      if (!form) return;
      const formData = new FormData(form);
      const action = form.action;
      button.disabled = true;

      try {
        const response = await fetch(action, {
          method: 'POST',
          body: formData,
        });
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        const data = await response.json();
        if (data.status !== 'ok') {
          throw new Error(data.message || 'Request failed');
        }

        // update count and active state
        if (data.update) {
          for (const [selector, html] of Object.entries(data.update)) {
            const el = document.querySelector(selector);
            if (el) {
              el.innerHTML = html;
            }
          }
        }
      } catch (err) {
        console.error(err);
        alert('Something went wrong. Please refresh the page.');
      } finally {
        button.disabled = false;
      }
    });
  }

  // Profile settings menu + panels
  const settingsToggle = document.getElementById('profileSettingsToggle');
  const settingsDropdown = document.getElementById('profileSettingsDropdown');
  const settingsPanels = document.querySelectorAll('.profile-settings-panel');

  const closeSettingsMenu = () => {
    settingsDropdown?.classList.remove('active');
  };

  if (settingsToggle && settingsDropdown) {
    settingsToggle.addEventListener('click', () => {
      settingsDropdown.classList.toggle('active');
    });

    document.addEventListener('click', (event) => {
      if (!settingsDropdown.contains(event.target) && !settingsToggle.contains(event.target)) {
        closeSettingsMenu();
      }
    });

    settingsDropdown.addEventListener('click', (event) => {
      const button = event.target.closest('[data-profile-action]');
      if (!button) return;
      event.preventDefault();
      const panel = button.getAttribute('data-profile-action');
      settingsPanels.forEach((p) => p.classList.toggle('active', p.dataset.panel === panel));
      closeSettingsMenu();
      const firstInput = document.querySelector(`.profile-settings-panel.active input, .profile-settings-panel.active textarea`);
      if (firstInput instanceof HTMLElement) {
        firstInput.focus();
      }
    });
  }

  // Notifications dropdown
  const notificationsToggle = document.getElementById('notificationsToggle');
  const notificationsDropdown = document.getElementById('notificationsDropdown');

  const closeNotifications = () => {
    notificationsDropdown?.classList.remove('active');
    if (notificationsToggle) {
      notificationsToggle.setAttribute('aria-expanded', 'false');
    }
  };

  if (notificationsToggle && notificationsDropdown) {
    const handleNotificationsToggle = async () => {
      const isOpen = notificationsDropdown.classList.toggle('active');
      notificationsToggle.setAttribute('aria-expanded', String(isOpen));

      if (isOpen) {
        // Mark notifications as read and clear badge
        try {
          await fetch('notifications.php?action=mark_read', { method: 'POST' });
          const badge = notificationsToggle.querySelector('.notification-badge');
          if (badge) {
            badge.remove();
          }
        } catch (err) {
          // ignore network errors
        }
      }
    };

    notificationsToggle.addEventListener('click', handleNotificationsToggle);
    window.toggleNotifications = handleNotificationsToggle;

    document.addEventListener('click', (event) => {
      if (!notificationsDropdown.contains(event.target) && !notificationsToggle.contains(event.target)) {
        closeNotifications();
      }
    });
  }

  // Messages dropdown
  const messagesToggle = document.getElementById('messagesToggle');
  const messagesDropdown = document.getElementById('messagesDropdown');
  const messageBadge = document.querySelector('.message-badge');
  const conversationsList = document.querySelector('.conversations');
  const messagesThreadHeader = document.querySelector('.messages-thread-header');
  const messagesList = document.querySelector('.messages-list');
  const messagesForm = document.querySelector('.messages-send-form');
  const messagesTextarea = messagesForm?.querySelector('textarea');
  const messagesToInput = messagesForm?.querySelector('input[name="to"]');

  let activeConversationUserId = null;

  const closeMessages = () => {
    messagesDropdown?.classList.remove('active');
    if (messagesToggle) {
      messagesToggle.setAttribute('aria-expanded', 'false');
    }
  };

  const renderConversations = (conversations) => {
    if (!conversationsList) return;
    conversationsList.innerHTML = '';

    conversations.forEach((conv) => {
      const item = document.createElement('li');
      item.className = 'conversation';
      item.dataset.userId = conv.user_id;

      if (activeConversationUserId === conv.user_id) {
        item.classList.add('active');
      }

      const avatar = document.createElement('span');
      avatar.className = 'avatar';
      if (conv.profile_pic) {
        const img = document.createElement('img');
        img.src = conv.profile_pic;
        img.alt = conv.full_name;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        avatar.appendChild(img);
      } else {
        avatar.textContent = conv.full_name?.[0] || '?';
      }

      const info = document.createElement('span');
      info.className = 'info';
      const name = document.createElement('strong');
      name.textContent = conv.full_name || conv.username;
      const meta = document.createElement('span');
      meta.textContent = conv.unread_count > 0 ? `${conv.unread_count} new` : '';

      info.appendChild(name);
      info.appendChild(meta);

      item.appendChild(avatar);
      item.appendChild(info);

      item.addEventListener('click', () => {
        activeConversationUserId = parseInt(item.dataset.userId, 10);
        document.querySelectorAll('.conversation').forEach((el) => el.classList.remove('active'));
        item.classList.add('active');
        loadMessages(activeConversationUserId, conv.full_name || conv.username);
      });

      conversationsList.appendChild(item);
    });
  };

  const renderMessages = (messages) => {
    if (!messagesList) return;
    messagesList.innerHTML = '';
    messages.forEach((msg) => {
      const el = document.createElement('div');
      el.className = 'message' + (msg.sender_id === window.MINIBOOK_USER_ID ? ' own' : '');

      const meta = document.createElement('div');
      meta.className = 'meta';
      meta.textContent = (msg.sender_id === window.MINIBOOK_USER_ID ? 'You' : msg.sender_name) + ' · ' + new Date(msg.created_at).toLocaleString();

      const text = document.createElement('div');
      text.textContent = msg.content;

      const actions = document.createElement('div');
      actions.className = 'actions';
      const more = document.createElement('button');
      more.type = 'button';
      more.textContent = '⋯';
      more.title = 'Actions';
      more.addEventListener('click', async () => {
        if (!confirm('Delete this message?')) return;
        await fetch('messages.php?action=delete', {
          method: 'POST',
          body: new URLSearchParams({ id: msg.id }),
        });
        loadMessages(activeConversationUserId, messagesThreadHeader.textContent);
      });
      actions.appendChild(more);

      el.appendChild(meta);
      el.appendChild(text);
      el.appendChild(actions);

      messagesList.appendChild(el);
    });
    messagesList.scrollTop = messagesList.scrollHeight;
  };

  const loadConversations = async () => {
    try {
      const res = await fetch('messages.php?action=conversations');
      const data = await res.json();
      if (data.status !== 'ok') return;
      if (messageBadge) {
        const totalUnread = data.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
        if (totalUnread > 0) {
          messageBadge.textContent = totalUnread;
          messageBadge.style.display = 'inline-flex';
        } else {
          messageBadge.style.display = 'none';
        }
      }
      renderConversations(data.conversations || []);
    } catch (err) {
      // ignore
    }
  };

  const loadMessages = async (userId, name) => {
    if (!messagesThreadHeader) return;
    messagesThreadHeader.textContent = name;
    if (messagesForm && messagesToInput) {
      messagesForm.style.display = 'flex';
      messagesToInput.value = String(userId);
    }

    try {
      const res = await fetch(`messages.php?action=messages&with=${userId}`);
      const data = await res.json();
      if (data.status !== 'ok') return;
      renderMessages(data.messages || []);
      await loadConversations();
    } catch (err) {
      // ignore
    }
  };

  if (messagesToggle && messagesDropdown) {
    messagesToggle.addEventListener('click', async () => {
      const isOpen = messagesDropdown.classList.toggle('active');
      messagesToggle.setAttribute('aria-expanded', String(isOpen));
      if (isOpen) {
        await loadConversations();
      }
    });

    document.addEventListener('click', (event) => {
      if (!messagesDropdown.contains(event.target) && !messagesToggle.contains(event.target)) {
        closeMessages();
      }
    });
  }

  if (messagesForm) {
    messagesForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!messagesToInput || !messagesTextarea) return;
      const to = messagesToInput.value;
      const content = messagesTextarea.value.trim();
      if (!to || content === '') return;

      try {
        const res = await fetch('messages.php?action=send', {
          method: 'POST',
          body: new URLSearchParams({ to, content }),
        });
        const data = await res.json();
        if (data.status !== 'ok') return;
        messagesTextarea.value = '';
        loadMessages(parseInt(to, 10), messagesThreadHeader.textContent);
      } catch (err) {
        // ignore
      }
    });
  }
})();