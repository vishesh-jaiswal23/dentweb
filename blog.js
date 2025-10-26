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
    return `blog-post.html?slug=${encodeURIComponent(post.slug)}`;
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
})();
