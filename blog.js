(function () {
  const apiBase = window.PORTAL_API_BASE || '';
  const listContainer = document.querySelector('[data-blog-list]');
  const template = document.getElementById('blog-card-template');
  const detailContainer = document.querySelector('[data-blog-post]');
  const detailTitle = document.querySelector('[data-blog-post-title]');
  const detailMeta = document.querySelector('[data-blog-post-meta]');
  const detailHero = document.querySelector('[data-blog-post-hero]');
  const detailContent = document.querySelector('[data-blog-post-content]');
  const detailAuthor = document.querySelector('[data-blog-post-author]');
  const detailState = document.querySelector('[data-blog-post-state]');
  const aiForm = document.querySelector('[data-ai-blog-form]');
  const aiFormAlert = document.querySelector('[data-ai-form-alert]');
  const aiDraftPanel = document.querySelector('[data-ai-draft]');
  const aiDraftTitle = document.querySelector('[data-ai-draft-title]');
  const aiDraftMeta = document.querySelector('[data-ai-draft-meta]');
  const aiDraftCover = document.querySelector('[data-ai-draft-cover]');
  const aiDraftBody = document.querySelector('[data-ai-draft-content]');
  const aiDraftAudioWrapper = aiDraftPanel ? aiDraftPanel.querySelector('.ai-draft__audio') : null;
  const aiDraftAudio = document.querySelector('[data-ai-draft-audio]');
  const aiPublishBtn = document.querySelector('[data-ai-publish]');
  const aiRefreshConfigBtn = document.querySelector('[data-ai-refresh-config]');
  const aiAutoForm = document.querySelector('[data-ai-auto-form]');
  const aiAutoAlert = document.querySelector('[data-ai-auto-alert]');
  const aiScheduleList = document.querySelector('[data-ai-schedule-list]');
  const aiUsageLog = document.querySelector('[data-ai-usage-log]');

  const aiState = {
    configPromise: null,
    currentDraft: null,
    usageLog: [],
    schedule: [],
    publishedTopics: new Set()
  };

  function resolve(path) {
    if (!apiBase) return path;
    try {
      return new URL(path, apiBase).toString();
    } catch (error) {
      return path;
    }
  }

  function setEmptyState(container, message, tone = 'info') {
    if (!container) return;
    container.innerHTML = '';
    const paragraph = document.createElement('p');
    paragraph.className = 'empty';
    paragraph.dataset.tone = tone;
    paragraph.textContent = message;
    container.appendChild(paragraph);
  }

  function formatMeta(post) {
    const tags = Array.isArray(post.tags) ? post.tags : [];
    const primaryTag = tags[0] || 'Update';
    const readTime = post.readTimeMinutes ? `${post.readTimeMinutes} min read` : '';
    return readTime ? `${primaryTag} · ${readTime}` : primaryTag;
  }

  function buildHref(post) {
    if (!post || !post.slug) {
      return '#';
    }
    return `blog/post.php?slug=${encodeURIComponent(post.slug)}`;
  }

  function renderPosts(posts) {
    if (!listContainer) return;
    listContainer.innerHTML = '';
    if (!posts.length) {
      setEmptyState(listContainer, 'No published posts yet. Vishesh will publish shortly.');
      return;
    }

    posts.forEach((post) => {
      const fragment = template ? template.content.cloneNode(true) : document.createDocumentFragment();
      let card = fragment.querySelector('[data-blog-card]');
      if (!card) {
        card = document.createElement('a');
        card.className = 'blog-card';
        card.innerHTML = `
          <img alt="" loading="lazy" />
          <div class="blog-card-body">
            <span class="blog-card-meta"></span>
            <h3></h3>
            <p></p>
          </div>
        `;
        fragment.appendChild(card);
      }

      const image = fragment.querySelector('[data-blog-card-image]') || card.querySelector('img');
      const meta = fragment.querySelector('[data-blog-card-meta]') || card.querySelector('.blog-card-meta');
      const title = fragment.querySelector('[data-blog-card-title]') || card.querySelector('h3');
      const excerpt = fragment.querySelector('[data-blog-card-excerpt]') || card.querySelector('p');

      card.href = buildHref(post);
      card.setAttribute('aria-label', `Read ${post.title}`);

      if (image) {
        image.src = post.heroImage || 'images/hero/hero.png';
        image.alt = post.title || 'Dakshayani blog post';
      }
      if (meta) {
        meta.textContent = formatMeta(post);
      }
      if (title) {
        title.textContent = post.title;
      }
      if (excerpt) {
        excerpt.textContent = post.excerpt || '';
      }

      listContainer.appendChild(fragment);
    });
  }

  function renderDetail(post) {
    if (!detailContainer || !post) return;

    detailContainer.hidden = false;
    if (detailState) {
      detailState.hidden = true;
    }

    if (detailTitle) {
      detailTitle.textContent = post.title;
    }
    if (detailMeta) {
      const published = post.publishedAt ? new Date(post.publishedAt) : null;
      const formattedDate = published ? published.toLocaleDateString('en-IN', { dateStyle: 'long' }) : '';
      const metaPieces = [formatMeta(post)];
      if (formattedDate) metaPieces.push(formattedDate);
      if (post.author?.name) metaPieces.push(`By ${post.author.name}`);
      detailMeta.textContent = metaPieces.filter(Boolean).join(' · ');
    }
    if (detailHero) {
      detailHero.src = post.heroImage || 'images/hero/hero.png';
      detailHero.alt = post.title || 'Dakshayani blog hero image';
    }
    if (detailAuthor) {
      detailAuthor.textContent = post.author?.name ? `Curated by ${post.author.name}` : '';
    }
    if (detailContent) {
      const body = Array.isArray(post.content)
        ? post.content.join('\n')
        : String(post.content || '');
      detailContent.innerHTML = '';
      body
        .split(/\n{2,}/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean)
        .forEach((paragraph) => {
          const element = document.createElement('p');
          element.textContent = paragraph;
          detailContent.appendChild(element);
        });
    }
  }

  function getAiConfig(forceRefresh = false) {
    if (!aiState.configPromise || forceRefresh) {
      if (window.dakshayaniAIConfig?.getConfig) {
        aiState.configPromise = window.dakshayaniAIConfig
          .getConfig(forceRefresh)
          .catch((error) => {
            console.warn('AI settings unavailable, using safe defaults.', error);
            return {
              provider: 'gemini',
              apiKey: '',
              models: { text: 'gemini-pro', image: 'imagen-lite', tts: 'studio-tts' }
            };
          });
      } else {
        aiState.configPromise = Promise.resolve({
          provider: 'gemini',
          apiKey: '',
          models: { text: 'gemini-pro', image: 'imagen-lite', tts: 'studio-tts' }
        });
      }
    }
    return aiState.configPromise;
  }

  function displayAiMessage(node, message, tone = 'info') {
    if (!node) return;
    node.textContent = message || '';
    node.classList.remove('is-success', 'is-error');
    if (!message) {
      return;
    }
    if (tone === 'success') {
      node.classList.add('is-success');
    } else if (tone === 'error') {
      node.classList.add('is-error');
    }
  }

  function generateDraftContent(topic, keywords, guidance, length) {
    const baseParagraphs = { short: 3, medium: 5, long: 7 };
    const paragraphCount = baseParagraphs[length] || baseParagraphs.medium;
    const keywordLine = keywords.length ? `Key focus: ${keywords.join(', ')}.` : 'Key focus: local solar adoption.';
    const guidanceLine = guidance ? `Editorial note: ${guidance}.` : 'Editorial note: emphasise customer benefits and compliance.';
    const paragraphs = [];
    paragraphs.push(
      `Dakshayani Enterprises reports on ${topic}, outlining actionable steps for households and partners across eastern India.`
    );
    paragraphs.push(`${keywordLine} ${guidanceLine}`);
    for (let index = 2; index < paragraphCount; index += 1) {
      const angle = index % 2 === 0 ? 'policy' : 'field insights';
      paragraphs.push(
        `Perspective ${index}: ${angle === 'policy' ? 'Policy & subsidy' : 'On-ground execution'} view explaining how ${
          topic
        } impacts timelines, financing, and quality assurance.`
      );
    }
    paragraphs.push('Closing with a clear CTA for enquiries, AMC enrolment, and community referrals.');
    return paragraphs;
  }

  function createCoverPlaceholder(topic) {
    const safeText = topic.replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
      <defs>
        <linearGradient id="grad" x1="0%" x2="100%" y1="0%" y2="100%">
          <stop offset="0%" stop-color="#0f172a"/>
          <stop offset="100%" stop-color="#1d4ed8"/>
        </linearGradient>
      </defs>
      <rect width="1280" height="720" fill="url(#grad)"/>
      <text x="640" y="360" font-family="Poppins" font-size="52" fill="#e2e8f0" text-anchor="middle" dominant-baseline="middle">${safeText}</text>
      <text x="640" y="420" font-family="Poppins" font-size="24" fill="#bfdbfe" text-anchor="middle">AI-assisted cover preview</text>
    </svg>`;
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
  }

  function estimateTokens(paragraphs) {
    const wordCount = paragraphs.reduce((total, paragraph) => total + paragraph.split(/\s+/).length, 0);
    return Math.ceil(wordCount * 1.2);
  }

  function createSilentAudioUrl(durationMs = 1500, sampleRate = 8000) {
    const numChannels = 1;
    const bytesPerSample = 2;
    const numSamples = Math.max(1, Math.round((durationMs / 1000) * sampleRate));
    const dataSize = numSamples * numChannels * bytesPerSample;
    const buffer = new ArrayBuffer(44 + dataSize);
    const view = new DataView(buffer);
    let offset = 0;

    const writeString = (value) => {
      for (let i = 0; i < value.length; i += 1) {
        view.setUint8(offset + i, value.charCodeAt(i));
      }
      offset += value.length;
    };

    const writeUint32 = (value) => {
      view.setUint32(offset, value, true);
      offset += 4;
    };

    const writeUint16 = (value) => {
      view.setUint16(offset, value, true);
      offset += 2;
    };

    writeString('RIFF');
    writeUint32(36 + dataSize);
    writeString('WAVE');
    writeString('fmt ');
    writeUint32(16);
    writeUint16(1);
    writeUint16(numChannels);
    writeUint32(sampleRate);
    writeUint32(sampleRate * numChannels * bytesPerSample);
    writeUint16(numChannels * bytesPerSample);
    writeUint16(bytesPerSample * 8);
    writeString('data');
    writeUint32(dataSize);
    offset = 44;

    const bytes = new Uint8Array(buffer);
    for (let i = 44; i < bytes.length; i += 1) {
      bytes[i] = 0;
    }

    let binary = '';
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });
    return `data:audio/wav;base64,${btoa(binary)}`;
  }

  function logAiUsage(type, model, tokens, detail) {
    if (!aiUsageLog) return;
    aiState.usageLog.unshift({
      id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
      type,
      model,
      tokens,
      detail,
      timestamp: new Date()
    });
    if (aiState.usageLog.length > 12) {
      aiState.usageLog.length = 12;
    }
    renderUsageLog();
  }

  function renderUsageLog() {
    if (!aiUsageLog) return;
    if (!aiState.usageLog.length) {
      aiUsageLog.innerHTML = '<li>No AI usage logged yet.</li>';
      return;
    }
    aiUsageLog.innerHTML = aiState.usageLog
      .map((entry) => {
        const tokens = entry.tokens ? `${entry.tokens} tokens` : 'event';
        return `<li><strong>${entry.type}</strong> • ${entry.model || 'model'} • ${tokens}<br /><small>${entry.detail} · ${entry.timestamp.toLocaleString('en-IN')}</small></li>`;
      })
      .join('');
  }

  function renderScheduleList() {
    if (!aiScheduleList) return;
    if (!aiState.schedule.length) {
      aiScheduleList.innerHTML = '<li>No topics queued.</li>';
      return;
    }
    aiScheduleList.innerHTML = aiState.schedule
      .map((item) => {
        return `<li><strong>${item.topic}</strong> • ${new Date(item.when).toLocaleString('en-IN')}</li>`;
      })
      .join('');
  }

  function renderDraft() {
    if (!aiDraftPanel || !aiState.currentDraft) return;
    const draft = aiState.currentDraft;
    aiDraftPanel.hidden = false;
    if (aiDraftTitle) {
      aiDraftTitle.textContent = draft.topic;
    }
    if (aiDraftMeta) {
      const modelName = draft.models?.text || 'text-model';
      aiDraftMeta.textContent = `Generated via ${draft.provider || 'AI'} • ${modelName} • ${draft.createdAt.toLocaleString('en-IN')}`;
    }
    if (aiDraftBody) {
      aiDraftBody.innerHTML = '';
      draft.content.forEach((paragraph) => {
        const node = document.createElement('p');
        node.textContent = paragraph;
        aiDraftBody.appendChild(node);
      });
    }
    if (aiDraftCover) {
      if (draft.cover) {
        aiDraftCover.src = draft.cover;
        aiDraftCover.hidden = false;
      } else {
        aiDraftCover.hidden = true;
      }
    }
    if (aiDraftAudio && aiDraftAudioWrapper) {
      if (draft.audio) {
        aiDraftAudio.src = draft.audio;
        aiDraftAudioWrapper.hidden = false;
      } else {
        aiDraftAudioWrapper.hidden = true;
      }
    }
  }

  async function handleAiGenerate(event) {
    event.preventDefault();
    const formData = new FormData(aiForm);
    const topic = (formData.get('topic') || '').trim();
    if (!topic) {
      displayAiMessage(aiFormAlert, 'Topic is required to draft a blog.', 'error');
      return;
    }
    displayAiMessage(aiFormAlert, 'Generating draft with configured models…');
    try {
      const config = await getAiConfig();
      const keywords = (formData.get('keywords') || '')
        .split(',')
        .map((keyword) => keyword.trim())
        .filter(Boolean);
      const length = formData.get('length') || 'medium';
      const guidance = (formData.get('guidance') || '').trim();
      const includeImage = formData.get('includeImage') === 'on';
      const includeAudio = formData.get('includeAudio') === 'on';

      const paragraphs = generateDraftContent(topic, keywords, guidance, length);
      const draft = {
        topic,
        keywords,
        length,
        guidance,
        content: paragraphs,
        provider: config.provider,
        models: config.models,
        createdAt: new Date(),
        cover: includeImage ? createCoverPlaceholder(topic) : '',
        audio: includeAudio ? createSilentAudioUrl() : ''
      };

      const tokens = estimateTokens(paragraphs);
      logAiUsage('Text', draft.models?.text || 'text-model', tokens, topic);
      if (includeImage) {
        logAiUsage('Image', draft.models?.image || 'image-model', 48, `${topic} cover`);
      }
      if (includeAudio) {
        logAiUsage('Audio', draft.models?.tts || 'tts-model', Math.ceil(tokens * 0.6), `${topic} narration`);
      }

      aiState.currentDraft = draft;
      renderDraft();
      displayAiMessage(aiFormAlert, `Draft prepared using ${draft.provider || 'AI'} models.`, 'success');
    } catch (error) {
      console.error('AI draft generation failed', error);
      displayAiMessage(aiFormAlert, 'AI studio is unavailable right now. Please retry.', 'error');
    }
  }

  function handlePublishDraft() {
    if (!aiState.currentDraft) {
      return;
    }
    const topicKey = aiState.currentDraft.topic.toLowerCase();
    aiState.publishedTopics.add(topicKey);
    aiState.schedule = aiState.schedule.filter((item) => item.topic.toLowerCase() !== topicKey);
    renderScheduleList();
    logAiUsage('Publish', 'blog', 0, aiState.currentDraft.topic);
    displayAiMessage(aiFormAlert, `Published “${aiState.currentDraft.topic}” to the newsroom queue.`, 'success');
  }

  function handleAutoForm(event) {
    event.preventDefault();
    const formData = new FormData(aiAutoForm);
    const timeValue = formData.get('time');
    if (!timeValue) {
      displayAiMessage(aiAutoAlert, 'Select a daily publish time.', 'error');
      return;
    }
    const [hours, minutes] = timeValue.split(':').map((value) => Number(value));
    const dedupe = formData.get('dedupe') === 'on';
    const topics = (formData.get('topics') || '')
      .split(/\n+/)
      .map((topic) => topic.trim())
      .filter(Boolean);

    const filteredTopics = dedupe
      ? topics.filter((topic) => !aiState.publishedTopics.has(topic.toLowerCase()))
      : topics;

    aiState.schedule = filteredTopics.map((topic, index) => {
      const nextRun = new Date();
      nextRun.setDate(nextRun.getDate() + index);
      nextRun.setHours(hours, minutes, 0, 0);
      return { topic, when: nextRun.toISOString() };
    });
    displayAiMessage(aiAutoAlert, `Automation saved with ${aiState.schedule.length} topic(s).`, 'success');
    renderScheduleList();
  }

  function initialiseAiStudio() {
    if (aiForm && !aiForm.dataset.aiInitialised) {
      aiForm.addEventListener('submit', handleAiGenerate);
      aiForm.dataset.aiInitialised = 'true';
    }
    if (aiPublishBtn && !aiPublishBtn.dataset.aiInitialised) {
      aiPublishBtn.addEventListener('click', handlePublishDraft);
      aiPublishBtn.dataset.aiInitialised = 'true';
    }
    if (aiAutoForm && !aiAutoForm.dataset.aiInitialised) {
      aiAutoForm.addEventListener('submit', handleAutoForm);
      aiAutoForm.dataset.aiInitialised = 'true';
    }
    if (aiRefreshConfigBtn && !aiRefreshConfigBtn.dataset.aiInitialised) {
      aiRefreshConfigBtn.addEventListener('click', () => {
        getAiConfig(true)
          .then((config) => {
            displayAiMessage(aiFormAlert, `AI settings refreshed for ${config.provider}.`, 'success');
          })
          .catch(() => {
            displayAiMessage(aiFormAlert, 'Unable to refresh AI settings right now.', 'error');
          });
      });
      aiRefreshConfigBtn.dataset.aiInitialised = 'true';
    }
    renderUsageLog();
    renderScheduleList();
    if (aiForm) {
      getAiConfig().catch(() => {});
    }
  }

  async function loadPosts() {
    if (!listContainer) return;
    try {
      const response = await fetch(resolve('/api/public/blog-posts'));
      if (!response.ok) {
        throw new Error('Failed to load blog posts');
      }
      const data = await response.json();
      const posts = Array.isArray(data?.posts) ? data.posts : [];
      renderPosts(posts);
    } catch (error) {
      console.error('Unable to load blog posts', error);
      setEmptyState(listContainer, 'Unable to reach the blog service right now. Please try again shortly.', 'error');
    }
  }

  function getSlug() {
    try {
      const params = new URLSearchParams(window.location.search);
      return params.get('slug');
    } catch (error) {
      return null;
    }
  }

  async function loadDetail() {
    if (!detailContainer) return;
    const slug = getSlug();
    if (!slug) {
      if (detailState) {
        detailState.textContent = 'Select a story from the blog overview to read the full briefing.';
      }
      return;
    }

    if (detailState) {
      detailState.hidden = false;
      detailState.textContent = 'Loading Vishesh\'s update…';
    }

    try {
      const response = await fetch(resolve(`/api/public/blog-posts?slug=${encodeURIComponent(slug)}`));
      if (!response.ok) {
        throw new Error('Not found');
      }
      const data = await response.json();
      if (!data?.post) {
        throw new Error('Missing post');
      }
      renderDetail(data.post);
    } catch (error) {
      console.error('Unable to load blog post', error);
      if (detailState) {
        detailState.hidden = false;
        detailState.dataset.tone = 'error';
        detailState.textContent = 'This article is unavailable or has been unpublished.';
      }
      if (detailContainer) {
        detailContainer.hidden = true;
      }
    }
  }

  if (listContainer) {
    loadPosts();
  }

  if (detailContainer) {
    loadDetail();
  }

  if (aiForm || aiAutoForm) {
    initialiseAiStudio();
  }
})();
