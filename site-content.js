(function () {
  const endpoint = '/api/public/site-content';
  const root = document.documentElement;
  const heroTitle = document.querySelector('[data-hero-title]');
  const heroSubtitle = document.querySelector('[data-hero-subtitle]');
  const heroImage = document.querySelector('[data-hero-main-image]');
  const heroCaption = document.querySelector('[data-hero-main-caption]');
  const bubbleHeading = document.querySelector('[data-hero-bubble-heading]');
  const bubbleBody = document.querySelector('[data-hero-bubble-body]');
  const heroPoints = document.querySelector('[data-hero-points]');
  const heroAnnouncement = document.querySelector('[data-hero-announcement]');
  const heroSection = document.querySelector('#hero');
  const offersList = document.querySelector('[data-offers-list]');
  const testimonialList = document.querySelector('[data-testimonial-list]');
  const sectionsHost = document.querySelector('[data-home-sections]');
  const sectionsList = sectionsHost ? sectionsHost.querySelector('[data-home-sections-list]') : null;

  function normaliseHex(input, fallback = '#000000') {
    if (typeof input !== 'string') return fallback;
    let value = input.trim();
    if (!value) return fallback;
    if (!value.startsWith('#')) value = `#${value}`;
    const shortMatch = /^#([0-9a-f]{3})$/i.exec(value);
    if (shortMatch) {
      const [, short] = shortMatch;
      return `#${short[0]}${short[0]}${short[1]}${short[1]}${short[2]}${short[2]}`.toUpperCase();
    }
    const fullMatch = /^#([0-9a-f]{6})$/i.exec(value);
    if (fullMatch) {
      return `#${fullMatch[1].toUpperCase()}`;
    }
    return fallback;
  }

  function hexToRgb(hex) {
    const normalised = normaliseHex(hex, '#000000');
    const value = normalised.slice(1);
    return {
      r: parseInt(value.slice(0, 2), 16),
      g: parseInt(value.slice(2, 4), 16),
      b: parseInt(value.slice(4, 6), 16),
    };
  }

  function mixHex(foreground, background, ratio) {
    const safeRatio = Math.min(Math.max(Number(ratio) || 0, 0), 1);
    const fg = hexToRgb(foreground);
    const bg = hexToRgb(background);
    const r = Math.round(fg.r * (1 - safeRatio) + bg.r * safeRatio);
    const g = Math.round(fg.g * (1 - safeRatio) + bg.g * safeRatio);
    const b = Math.round(fg.b * (1 - safeRatio) + bg.b * safeRatio);
    return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b
      .toString(16)
      .padStart(2, '0')}`.toUpperCase();
  }

  function relativeLuminance(hex) {
    const { r, g, b } = hexToRgb(hex);
    const [rn, gn, bn] = [r, g, b].map((channel) => {
      const value = channel / 255;
      return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return rn * 0.2126 + gn * 0.7152 + bn * 0.0722;
  }

  function contrastRatio(colorA, colorB) {
    const luminanceA = relativeLuminance(colorA);
    const luminanceB = relativeLuminance(colorB);
    const [lighter, darker] = luminanceA > luminanceB ? [luminanceA, luminanceB] : [luminanceB, luminanceA];
    return (lighter + 0.05) / (darker + 0.05);
  }

  function contrastText(background) {
    const luminance = relativeLuminance(background);
    return luminance > 0.5 ? '#111827' : '#FFFFFF';
  }

  function ensureTextColor(background, preferred, desired = 4.5) {
    const bg = normaliseHex(background, '#FFFFFF');
    const fallback = contrastText(bg);
    const base = preferred ? normaliseHex(preferred, fallback) : fallback;

    let bestColor = base;
    let bestRatio = contrastRatio(bestColor, bg);

    if (bestRatio >= desired) {
      return bestColor;
    }

    const fallbackRatio = contrastRatio(fallback, bg);
    if (fallbackRatio > bestRatio) {
      bestColor = fallback;
      bestRatio = fallbackRatio;
      if (bestRatio >= desired) {
        return bestColor;
      }
    }

    if (base !== fallback) {
      for (let step = 1; step <= 10; step += 1) {
        const candidate = mixHex(base, fallback, step / 10);
        const ratio = contrastRatio(candidate, bg);
        if (ratio >= desired) {
          return candidate;
        }
        if (ratio > bestRatio) {
          bestColor = candidate;
          bestRatio = ratio;
        }
      }
    }

    return bestColor;
  }

  function ensureMutedColor(background, textColor, preferred, desired = 4.5) {
    const bg = normaliseHex(background, '#FFFFFF');
    const text = normaliseHex(textColor, contrastText(bg));
    const fallbackBase = mixHex(text, bg, 0.65);
    const base = preferred ? normaliseHex(preferred, fallbackBase) : fallbackBase;

    let bestColor = base;
    let bestRatio = contrastRatio(bestColor, bg);

    if (bestRatio >= desired) {
      return bestColor;
    }

    const mixSteps = [0.6, 0.55, 0.5, 0.45, 0.4, 0.35, 0.3, 0.25, 0.2, 0.15, 0.1, 0.05, 0];
    for (const amount of mixSteps) {
      const candidate = mixHex(text, bg, amount);
      const ratio = contrastRatio(candidate, bg);
      if (ratio >= desired) {
        return candidate;
      }
      if (ratio > bestRatio) {
        bestColor = candidate;
        bestRatio = ratio;
      }
    }

    const textFallback = ensureTextColor(bg, text, desired);
    const fallbackRatio = contrastRatio(textFallback, bg);
    if (fallbackRatio > bestRatio) {
      return textFallback;
    }

    return bestColor;
  }

  function toRgba(hex, alpha) {
    const { r, g, b } = hexToRgb(hex);
    const safeAlpha = Math.min(Math.max(Number(alpha) || 0, 0), 1);
    return `rgba(${r}, ${g}, ${b}, ${safeAlpha})`;
  }

  function renderEmptyState(container, message, className = 'site-search-empty') {
    if (!container) return;
    container.innerHTML = '';
    const paragraph = document.createElement('p');
    paragraph.className = className;
    paragraph.textContent = message;
    container.appendChild(paragraph);
  }

  function renderSections(sections) {
    if (!sectionsHost || !sectionsList) return;

    if (!Array.isArray(sections) || sections.length === 0) {
      sectionsList.innerHTML = '';
      sectionsHost.hidden = true;
      return;
    }

    const fragment = document.createDocumentFragment();

    sections.forEach((section) => {
      const block = document.createElement('section');
      block.className = 'home-dynamic-section';
      const tone = section.backgroundStyle || 'section';
      block.setAttribute('data-section-tone', tone);
      if (section.id) {
        block.id = `home-section-${section.id}`;
      }

      const container = document.createElement('div');
      container.className = 'container home-dynamic-section__inner';

      const grid = document.createElement('div');
      grid.className = 'home-dynamic-section__grid';

      const content = document.createElement('div');
      content.className = 'home-dynamic-section__content';

      if (section.eyebrow) {
        const eyebrow = document.createElement('p');
        eyebrow.className = 'home-dynamic-section__eyebrow';
        eyebrow.textContent = section.eyebrow;
        content.appendChild(eyebrow);
      }

      if (section.title) {
        const title = document.createElement('h2');
        title.className = 'home-dynamic-section__title';
        title.textContent = section.title;
        content.appendChild(title);
      }

      if (section.subtitle) {
        const subtitle = document.createElement('p');
        subtitle.className = 'home-dynamic-section__subtitle';
        subtitle.textContent = section.subtitle;
        content.appendChild(subtitle);
      }

      if (Array.isArray(section.body)) {
        section.body.forEach((paragraph) => {
          if (typeof paragraph !== 'string' || !paragraph.trim()) return;
          const bodyParagraph = document.createElement('p');
          bodyParagraph.className = 'home-dynamic-section__paragraph';
          bodyParagraph.textContent = paragraph;
          content.appendChild(bodyParagraph);
        });
      }

      if (Array.isArray(section.bullets) && section.bullets.length) {
        const list = document.createElement('ul');
        list.className = 'home-dynamic-section__bullets';
        section.bullets.forEach((bullet) => {
          if (typeof bullet !== 'string' || !bullet.trim()) return;
          const item = document.createElement('li');
          const icon = document.createElement('i');
          icon.className = 'fa-solid fa-circle-check';
          icon.setAttribute('aria-hidden', 'true');
          item.appendChild(icon);
          item.appendChild(document.createTextNode(bullet));
          list.appendChild(item);
        });
        if (list.children.length) {
          content.appendChild(list);
        }
      }

      if (section.cta && section.cta.text && section.cta.url) {
        const actions = document.createElement('div');
        actions.className = 'home-dynamic-section__actions';
        const link = document.createElement('a');
        link.className = 'btn btn-secondary';
        link.href = section.cta.url;
        link.textContent = section.cta.text;
        link.target = '_blank';
        link.rel = 'noopener';
        actions.appendChild(link);
        content.appendChild(actions);
      }

      grid.appendChild(content);

      if (section.media && section.media.type === 'image' && section.media.src) {
        const figure = document.createElement('figure');
        figure.className = 'home-dynamic-section__media';
        const img = document.createElement('img');
        img.src = section.media.src;
        img.loading = 'lazy';
        img.alt = section.media.alt || section.title || 'Dakshayani highlight';
        figure.appendChild(img);
        if (section.media.alt) {
          const caption = document.createElement('figcaption');
          caption.textContent = section.media.alt;
          figure.appendChild(caption);
        }
        grid.appendChild(figure);
      } else if (section.media && section.media.type === 'video' && section.media.src) {
        const mediaWrapper = document.createElement('div');
        mediaWrapper.className = 'home-dynamic-section__media';
        const video = document.createElement('video');
        video.src = section.media.src;
        video.controls = true;
        video.playsInline = true;
        mediaWrapper.appendChild(video);
        grid.appendChild(mediaWrapper);
      }

      container.appendChild(grid);
      block.appendChild(container);
      fragment.appendChild(block);
    });

    sectionsList.innerHTML = '';
    sectionsList.appendChild(fragment);
    sectionsHost.hidden = false;
  }

  function updateThemeBadges(theme) {
    const label = theme && typeof theme.seasonLabel === 'string' ? theme.seasonLabel.trim() : '';
    const announcement = theme && typeof theme.announcement === 'string' ? theme.announcement.trim() : '';
    const nodes = document.querySelectorAll('[data-site-theme-label]');

    nodes.forEach((node) => {
      if (!(node instanceof HTMLElement)) {
        return;
      }
      if (label === '') {
        node.textContent = '';
        node.hidden = true;
        node.removeAttribute('title');
        return;
      }

      node.textContent = label;
      node.hidden = false;
      if (announcement !== '') {
        node.setAttribute('title', announcement);
        node.dataset.themeAnnouncement = announcement;
      } else {
        node.removeAttribute('title');
        delete node.dataset.themeAnnouncement;
      }
    });
  }

  function updateHero(hero) {
    if (!hero) return;

    if (heroTitle && hero.title) {
      heroTitle.textContent = hero.title;
    }
    if (heroSubtitle && hero.subtitle) {
      heroSubtitle.textContent = hero.subtitle;
    }
    if (heroImage && hero.image) {
      heroImage.src = hero.image;
      heroImage.alt = hero.title || 'Dakshayani rooftop solar project';
    }
    if (heroCaption) {
      heroCaption.textContent = hero.imageCaption || '';
    }
    if (bubbleHeading) {
      bubbleHeading.textContent = hero.bubbleHeading || '';
    }
    if (bubbleBody) {
      bubbleBody.textContent = hero.bubbleBody || '';
    }
    if (heroPoints) {
      heroPoints.innerHTML = '';
      if (Array.isArray(hero.bullets) && hero.bullets.length) {
        hero.bullets.forEach((point) => {
          if (typeof point !== 'string' || !point.trim()) return;
          const li = document.createElement('li');
          const icon = document.createElement('i');
          icon.className = 'fa-solid fa-circle-check';
          icon.setAttribute('aria-hidden', 'true');
          li.appendChild(icon);
          li.appendChild(document.createTextNode(point));
          heroPoints.appendChild(li);
        });
      }
    }
  }

  function renderOffers(offers) {
    if (!offersList) return;
    if (!Array.isArray(offers) || !offers.length) {
      renderEmptyState(offersList, 'Admin will publish seasonal offers here soon.');
      return;
    }

    offersList.innerHTML = '';
    const fragment = document.createDocumentFragment();

    offers.forEach((offer) => {
      const card = document.createElement('article');
      card.className = 'offer-card';

      if (offer.image) {
        const media = document.createElement('img');
        media.className = 'offer-illustration';
        media.src = offer.image;
        media.alt = offer.title || 'Seasonal offer';
        media.loading = 'lazy';
        card.appendChild(media);
      }

      const header = document.createElement('div');
      header.className = 'offer-card-header';
      if (offer.badge) {
        const badge = document.createElement('span');
        badge.className = 'offer-badge';
        badge.textContent = offer.badge;
        header.appendChild(badge);
      }
      const title = document.createElement('h3');
      title.textContent = offer.title || 'Limited time offer';
      header.appendChild(title);
      card.appendChild(header);

      if (offer.description) {
        const description = document.createElement('p');
        description.textContent = offer.description;
        card.appendChild(description);
      }

      const validityParts = [];
      if (offer.startsOn) validityParts.push(`From ${offer.startsOn}`);
      if (offer.endsOn) validityParts.push(`Till ${offer.endsOn}`);
      if (validityParts.length) {
        const validity = document.createElement('p');
        validity.className = 'offer-validity';
        validity.textContent = validityParts.join(' · ');
        card.appendChild(validity);
      }

      if (offer.ctaText && offer.ctaUrl) {
        const actions = document.createElement('div');
        actions.className = 'offer-actions';
        const link = document.createElement('a');
        link.className = 'btn btn-secondary';
        link.href = offer.ctaUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = offer.ctaText;
        actions.appendChild(link);
        card.appendChild(actions);
      }

      fragment.appendChild(card);
    });

    offersList.appendChild(fragment);
  }

  function renderTestimonials(items) {
    if (!testimonialList) return;
    if (!Array.isArray(items) || !items.length) {
      renderEmptyState(testimonialList, 'Testimonials will appear here soon.');
      return;
    }

    testimonialList.innerHTML = '';
    const fragment = document.createDocumentFragment();

    items.forEach((item) => {
      const card = document.createElement('div');
      card.className = 'testimonial-card text-left';

      const quote = document.createElement('div');
      quote.className = 'quote-text';
      quote.textContent = item.quote || '';
      card.appendChild(quote);

      const author = document.createElement('div');
      author.className = 'author';

      if (item.image) {
        const avatar = document.createElement('img');
        avatar.src = item.image;
        avatar.alt = item.name || 'Customer photo';
        avatar.loading = 'lazy';
        avatar.className = 'author-avatar';
        author.appendChild(avatar);
      }

      const info = document.createElement('div');
      info.className = 'author-info';
      const name = document.createElement('p');
      name.className = 'author-name';
      const locationSuffix = item.location ? ` (${item.location})` : '';
      name.textContent = `${item.name || 'Customer'}${locationSuffix}`;
      info.appendChild(name);
      if (item.role) {
        const role = document.createElement('p');
        role.className = 'author-title';
        role.textContent = item.role;
        info.appendChild(role);
      }
      author.appendChild(info);
      card.appendChild(author);
      fragment.appendChild(card);
    });

    testimonialList.appendChild(fragment);
  }

  function applyPalette(palette) {
    if (!palette || typeof palette !== 'object') return;

    Object.entries(palette).forEach(([slug, entry]) => {
      if (!entry || typeof entry !== 'object') return;
      const background = normaliseHex(entry.background || '#FFFFFF', '#FFFFFF');
      const text = ensureTextColor(background, entry.text);
      const muted = ensureMutedColor(background, text, entry.muted);

      root.style.setProperty(`--theme-${slug}-background`, background);
      root.style.setProperty(`--theme-${slug}-text`, text);
      root.style.setProperty(`--theme-${slug}-muted`, muted);

      if (slug === 'surface') {
        root.style.setProperty('--surface', background);
        const bodyColor = mixHex(text, background, 0.18);
        const softText = mixHex(text, background, 0.45);
        const lightTone = mixHex(background, '#FFFFFF', 0.2);
        const midTone = mixHex(text, muted, 0.5);

        root.style.setProperty('--base-900', text);
        root.style.setProperty('--base-800', bodyColor);
        root.style.setProperty('--base-700', text);
        root.style.setProperty('--base-600', muted);
        root.style.setProperty('--base-500', midTone);
        root.style.setProperty('--base-400', softText);
        root.style.setProperty('--base-300', lightTone);
      }

      if (slug === 'section') {
        root.style.setProperty('--alt-surface', background);
      }

      if (slug === 'page') {
        document.body.style.backgroundColor = background;
        document.body.style.color = text;
        const metaTheme = document.querySelector('meta[name="theme-color"]');
        if (metaTheme) {
          metaTheme.setAttribute('content', background);
        }
      }

      if (slug === 'hero') {
        root.style.setProperty('--hero-surface', background);
        root.style.setProperty('--hero-foreground', text);
        const heroIsLight = relativeLuminance(background) > 0.55;
        const overlayBase = heroIsLight ? mixHex(background, text, 0.35) : mixHex(text, background, 0.85);
        const heroPanelSurface = heroIsLight
          ? mixHex(background, text, 0.15)
          : mixHex(text, background, 0.85);
        const heroPanelText = ensureTextColor(heroPanelSurface, text);
        const heroPanelMuted = ensureMutedColor(
          heroPanelSurface,
          heroPanelText,
          mixHex(heroPanelText, heroPanelSurface, heroIsLight ? 0.65 : 0.35)
        );
        const heroPanelBorder = mixHex(heroPanelText, heroPanelSurface, heroIsLight ? 0.65 : 0.25);

        root.style.setProperty('--hero-overlay-color', toRgba(overlayBase, 0.82));
        root.style.setProperty('--hero-subdued', ensureMutedColor(background, text, mixHex(text, background, 0.35)));
        root.style.setProperty('--hero-panel-surface', heroPanelSurface);
        root.style.setProperty('--hero-panel-border', heroPanelBorder);
        root.style.setProperty('--hero-panel-text', heroPanelText);
        root.style.setProperty('--hero-panel-muted', heroPanelMuted);
      }

      if (slug === 'callout') {
        root.style.setProperty('--callout-background', background);
        root.style.setProperty('--callout-text', text);
      }

      if (slug === 'footer') {
        root.style.setProperty('--footer-background', background);
        root.style.setProperty('--footer-text', text);
      }
    });
  }

  function applyTheme(theme) {
    if (!theme || typeof theme !== 'object') return;

    applyPalette(theme.palette || {});

    const accentColor = theme.accentColor ? normaliseHex(theme.accentColor, '#2563EB') : '#2563EB';
    const accentText = theme.palette && theme.palette.accent && theme.palette.accent.text
      ? normaliseHex(theme.palette.accent.text, contrastText(accentColor))
      : contrastText(accentColor);

    const accentSoft = mixHex(accentColor, '#FFFFFF', 0.55);
    const accentStrong = mixHex(accentColor, '#000000', 0.25);

    root.style.setProperty('--primary-main', accentColor);
    root.style.setProperty('--primary-dark', mixHex(accentColor, '#000000', 0.25));
    root.style.setProperty('--primary-light', mixHex(accentColor, '#FFFFFF', 0.35));
    root.style.setProperty('--accent-blue-main', accentColor);
    root.style.setProperty('--accent-blue-dark', mixHex(accentColor, '#000000', 0.2));
    root.style.setProperty('--theme-accent-text', accentText);
    root.style.setProperty('--accent-soft', accentSoft);
    root.style.setProperty('--accent-strong', accentStrong);
    root.style.setProperty('--accent-soft-text', contrastText(accentSoft));

    if (heroSection) {
      if (theme.backgroundImage) {
        const overlayValue = (root.style.getPropertyValue('--hero-overlay-color') || '').trim() || 'rgba(15, 23, 42, 0.82)';
        heroSection.style.backgroundImage = `linear-gradient(135deg, ${overlayValue}, rgba(15, 23, 42, 0.68)), url(${theme.backgroundImage})`;
        heroSection.style.backgroundSize = 'cover';
        heroSection.style.backgroundPosition = 'center';
      } else {
        heroSection.style.removeProperty('background-image');
      }
    }

    if (heroAnnouncement) {
      if (theme.announcement) {
        heroAnnouncement.textContent = theme.announcement;
        heroAnnouncement.hidden = false;
      } else {
        heroAnnouncement.hidden = true;
        heroAnnouncement.textContent = '';
      }
    }

    updateThemeBadges(theme);
  }

  const siteContentPromise = fetch(endpoint, { cache: 'no-store' })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`Failed to load site content (${response.status})`);
      }
      return response.json();
    })
    .then((data) => {
      const theme = data.theme || {};
      const hero = data.hero || {};
      const sections = data.sections || [];
      const offers = data.offers || [];
      const testimonials = data.testimonials || [];

      applyTheme(theme);
      updateHero(hero);
      renderSections(sections);
      renderOffers(offers);
      renderTestimonials(testimonials);

      const detail = { theme, hero, sections, offers, testimonials };
      document.dispatchEvent(new CustomEvent('dakshayani:site-content-ready', { detail }));

      return detail;
    })
    .catch((error) => {
      console.error('Unable to load site content', error);
      renderSections([]);
      renderEmptyState(offersList, 'Unable to load offers at the moment.');
      renderEmptyState(testimonialList, 'Unable to load testimonials at the moment.');
      if (heroAnnouncement) {
        heroAnnouncement.hidden = true;
        heroAnnouncement.textContent = '';
      }
      document.dispatchEvent(new CustomEvent('dakshayani:site-content-error', { detail: { error } }));
      throw error;
    });

  window.DakshayaniSiteContent = siteContentPromise;
  siteContentPromise.catch(() => {});
})();
