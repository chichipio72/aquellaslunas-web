(() => {
  'use strict';

  const root = document.querySelector('[data-event-infographic]');
  if (!root) return;
  const canvas = root.querySelector('canvas');
  const modelElement = root.querySelector('[data-infographic-model]');
  const status = root.querySelector('[data-infographic-status]');
  const download = root.querySelector('[data-infographic-download]');
  const share = root.querySelector('[data-infographic-share]');
  const moonSource = root.querySelector('[data-infographic-moon-source]');
  const eclipseSources = Array.from(root.querySelectorAll('[data-infographic-eclipse-source]'));
  if (!(canvas instanceof HTMLCanvasElement) || !modelElement || !(download instanceof HTMLButtonElement)) return;

  let model;
  try { model = JSON.parse(modelElement.textContent || '{}'); } catch (error) { return; }
  let moonCanvas = null;
  const eclipseImages = new Map();
  const pngCache = new Map();
  let renderReady = false;

  const formats = {
    story: { width: 1080, height: 1920, label: 'historia' },
    feed: { width: 1080, height: 1350, label: 'feed' },
  };

  const planetColors = {
    mercury: ['#c8c0b4', '#6e6962'], venus: ['#fff0b4', '#bc7d3e'],
    mars: ['#ef9a70', '#8d3926'], jupiter: ['#f2d0a7', '#a66c48'],
    saturn: ['#f4d9a4', '#a88958'], uranus: ['#b9f4f1', '#559da4'],
    neptune: ['#7ca8ff', '#284dba'],
  };

  function roundedRect(context, x, y, width, height, radius) {
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
  }

  function drawBackground(context, width, height) {
    const gradient = context.createLinearGradient(0, 0, width, height);
    gradient.addColorStop(0, '#07101f'); gradient.addColorStop(.5, '#040916'); gradient.addColorStop(1, '#0d1022');
    context.fillStyle = gradient; context.fillRect(0, 0, width, height);
    const glow = context.createRadialGradient(width * .7, height * .3, 0, width * .7, height * .3, width * .72);
    glow.addColorStop(0, 'rgba(85, 101, 170, .19)'); glow.addColorStop(.45, 'rgba(31, 47, 91, .08)'); glow.addColorStop(1, 'rgba(0, 0, 0, 0)');
    context.fillStyle = glow; context.fillRect(0, 0, width, height);
    context.fillStyle = 'rgba(220, 231, 255, .48)';
    for (let index = 0; index < 54; index += 1) {
      const x = ((index * 197 + 83) % 997) / 997 * width;
      const y = ((index * 283 + 41) % 919) / 919 * height;
      const radius = index % 7 === 0 ? 1.6 : .8;
      context.globalAlpha = .15 + (index % 5) * .06; context.beginPath(); context.arc(x, y, radius, 0, Math.PI * 2); context.fill();
    }
    context.globalAlpha = 1;
  }

  function fitFont(context, text, maximumWidth, initialSize, minimumSize, weight = 600) {
    let size = initialSize;
    do { context.font = `${weight} ${size}px system-ui, -apple-system, "Segoe UI", sans-serif`; size -= 2; }
    while (size >= minimumSize && context.measureText(text).width > maximumWidth);
    return size + 2;
  }

  function wrapLines(context, text, maximumWidth, maximumLines = 3) {
    const words = String(text).trim().split(/\s+/); const lines = []; let line = '';
    words.forEach((word) => {
      const candidate = line ? `${line} ${word}` : word;
      if (line && context.measureText(candidate).width > maximumWidth && lines.length < maximumLines - 1) { lines.push(line); line = word; }
      else line = candidate;
    });
    if (line) lines.push(line); return lines;
  }

  function drawWrappedText(context, text, x, y, maximumWidth, lineHeight, maximumLines = 3) {
    const lines = wrapLines(context, text, maximumWidth, maximumLines);
    lines.forEach((line, index) => context.fillText(line, x, y + index * lineHeight));
    return y + lines.length * lineHeight;
  }

  function drawMoonGlow(context, x, y, radius) {
    context.save();
    const glow = context.createRadialGradient(x, y, radius * .5, x, y, radius * 1.42);
    glow.addColorStop(0, 'rgba(215, 226, 255, .16)');
    glow.addColorStop(.68, 'rgba(164, 184, 231, .1)');
    glow.addColorStop(1, 'rgba(115, 139, 203, 0)');
    context.fillStyle = glow;
    context.beginPath(); context.arc(x, y, radius * 1.42, 0, Math.PI * 2); context.fill();
    context.restore();
  }

  function drawRealMoon(context, x, y, radius) {
    if (!(moonCanvas instanceof HTMLCanvasElement)) return;
    drawMoonGlow(context, x, y, radius);
    const drawSize = radius * 2 / .84;
    context.drawImage(moonCanvas, x - drawSize / 2, y - drawSize / 2, drawSize, drawSize);
  }

  function drawPlanet(context, id, x, y, radius) {
    const colors = planetColors[id] || ['#dce7ff', '#6f7ea3'];
    context.save(); context.shadowColor = colors[0]; context.shadowBlur = radius * 1.5;
    if (id === 'saturn') {
      context.strokeStyle = 'rgba(224, 202, 151, .75)'; context.lineWidth = radius * .28;
      context.beginPath(); context.ellipse(x, y, radius * 2.15, radius * .62, -.18, 0, Math.PI * 2); context.stroke();
    }
    const sphere = context.createRadialGradient(x - radius * .35, y - radius * .4, radius * .08, x, y, radius);
    sphere.addColorStop(0, colors[0]); sphere.addColorStop(1, colors[1]);
    context.fillStyle = sphere; context.beginPath(); context.arc(x, y, radius, 0, Math.PI * 2); context.fill(); context.restore();
  }

  function drawPlausibleMars(context, x, y, radius) {
    context.save();
    const halo = context.createRadialGradient(x, y, 0, x, y, radius * 7);
    halo.addColorStop(0, 'rgba(255, 211, 166, .5)');
    halo.addColorStop(.16, 'rgba(231, 133, 87, .2)');
    halo.addColorStop(1, 'rgba(210, 95, 61, 0)');
    context.fillStyle = halo; context.beginPath(); context.arc(x, y, radius * 7, 0, Math.PI * 2); context.fill();
    context.shadowColor = '#f19a71'; context.shadowBlur = radius * 1.8;
    context.fillStyle = '#e88b63'; context.beginPath(); context.arc(x, y, radius, 0, Math.PI * 2); context.fill();
    context.restore();
  }

  function drawConjunctionStoryRealistic(context, width, margin, contentWidth, editorial) {
    const directionX = Number.isFinite(Number(model.visual?.direction_x)) ? Number(model.visual.direction_x) : 1;
    const directionY = Number.isFinite(Number(model.visual?.direction_y)) ? Number(model.visual.direction_y) : 0;
    const directionLength = Math.hypot(directionX, directionY) || 1;
    const unitX = directionX / directionLength; const unitY = directionY / directionLength;
    const visualSeparation = editorial ? 440 : 310;
    const centerX = width / 2; const centerY = editorial ? 700 : 720;
    const moonX = centerX - unitX * visualSeparation / 2;
    const moonY = centerY - unitY * visualSeparation / 2;
    const moonRadius = editorial ? 165 : 150;
    const marsX = centerX + unitX * visualSeparation / 2;
    const marsY = centerY + unitY * visualSeparation / 2;
    const marsRadius = editorial ? 9 : 6;
    const glow = context.createRadialGradient(moonX, moonY, 0, moonX, moonY, editorial ? 440 : 380);
    glow.addColorStop(0, 'rgba(96, 116, 177, .13)'); glow.addColorStop(1, 'rgba(5, 10, 24, 0)');
    context.fillStyle = glow; context.fillRect(0, 340, width, 700);
    drawRealMoon(context, moonX, moonY, moonRadius);
    drawPlausibleMars(context, marsX, marsY, marsRadius);
    context.textAlign = 'center'; context.fillStyle = '#bac6dd';
    context.font = '560 23px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText('Marte', marsX, marsY + 55);
    context.textAlign = 'left';

    const explanationY = editorial ? 1045 : 1140;
    const boxY = editorial ? 1180 : 1300;
    const tipY = editorial ? 1435 : 1570;
    context.fillStyle = '#d3dbea'; context.font = '430 32px system-ui, -apple-system, "Segoe UI", sans-serif';
    drawWrappedText(context, model.explanation, margin, explanationY, contentWidth, 44, 2);
    roundedRect(context, margin, boxY, contentWidth, 180, 26); context.fillStyle = 'rgba(132, 151, 205, .075)'; context.fill();
    context.strokeStyle = 'rgba(173, 190, 231, .16)'; context.lineWidth = 1.5; context.stroke();
    context.fillStyle = '#f0d59b'; context.font = '650 37px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.fillText(model.recommended_time, margin + 30, boxY + 58);
    context.fillStyle = '#aebbd2'; context.font = '500 27px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.fillText(model.time_context, margin + 30, boxY + 104);
    if (model.observation_guide) {
      context.fillStyle = '#c4cee1'; context.font = '500 25px system-ui, -apple-system, "Segoe UI", sans-serif';
      context.fillText(model.observation_guide, margin + 30, boxY + 146);
    }
    context.fillStyle = '#aebbd2'; context.font = '430 28px system-ui, -apple-system, "Segoe UI", sans-serif';
    drawWrappedText(context, `Consejo: ${model.tip}`, margin, tipY, contentWidth, 39, 2);
  }

  function drawEclipseImage(context, image, x, y, size) {
    if (!(image instanceof HTMLCanvasElement) && !(image instanceof HTMLImageElement)) return;
    context.save();
    const glow = context.createRadialGradient(x, y, size * .12, x, y, size * .54);
    glow.addColorStop(0, model.visual.kind === 'solar' ? 'rgba(245, 220, 156, .18)' : 'rgba(176, 193, 235, .18)');
    glow.addColorStop(1, 'rgba(5, 10, 24, 0)');
    context.fillStyle = glow; context.fillRect(x - size * .65, y - size * .65, size * 1.3, size * 1.3);
    const sourceWidth = image instanceof HTMLImageElement ? image.naturalWidth : image.width;
    const sourceHeight = image instanceof HTMLImageElement ? image.naturalHeight : image.height;
    const sourceSize = Math.min(sourceWidth, sourceHeight);
    const sourceX = (sourceWidth - sourceSize) / 2;
    const sourceY = (sourceHeight - sourceSize) / 2;
    context.drawImage(image, sourceX, sourceY, sourceSize, sourceSize, x - size / 2, y - size / 2, size, size);
    context.restore();
  }

  function drawEclipseVisual(context, x, y, size, role = 'maximum') {
    drawEclipseImage(context, eclipseImages.get(role) || eclipseImages.get('maximum'), x, y, size);
  }

  function drawVisibilityPill(context, text, x, y) {
    context.font = '650 29px system-ui, -apple-system, "Segoe UI", sans-serif';
    const width = context.measureText(text).width + 58;
    roundedRect(context, x, y, width, 62, 31); context.fillStyle = 'rgba(211, 179, 111, .12)'; context.fill();
    context.strokeStyle = 'rgba(229, 202, 144, .28)'; context.stroke();
    context.fillStyle = '#f0d59b'; context.fillText(text, x + 29, y + 41);
  }

  function drawStoryVariantA(context, width, margin, contentWidth) {
    drawEclipseVisual(context, width / 2, 650, 520, 'maximum');
    context.fillStyle = '#9aa9ca'; context.font = '650 22px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.letterSpacing = '2px'; context.fillText('CÓMO VA A CAMBIAR', margin, 965); context.letterSpacing = '0px';
    roundedRect(context, margin, 995, contentWidth, 330, 30); context.fillStyle = 'rgba(132, 151, 205, .075)'; context.fill();
    context.strokeStyle = 'rgba(173, 190, 231, .15)'; context.stroke();
    const roles = ['start', 'maximum', 'end'];
    const moments = Array.isArray(model.moments) ? model.moments : [];
    roles.forEach((role, index) => {
      const x = margin + 155 + index * ((contentWidth - 310) / 2);
      if (index < 2) { context.strokeStyle = 'rgba(176, 190, 222, .23)'; context.lineWidth = 2; context.beginPath(); context.moveTo(x + 105, 1110); context.lineTo(x + 205, 1110); context.stroke(); }
      drawEclipseVisual(context, x, 1110, role === 'maximum' ? 205 : 175, role);
      context.textAlign = 'center'; context.fillStyle = role === 'maximum' ? '#f0d59b' : '#c8d2e7'; context.font = '650 25px system-ui, -apple-system, "Segoe UI", sans-serif';
      context.fillText(moments[index]?.label || '', x, 1252);
      context.fillStyle = '#f2f5fc'; context.font = '650 30px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText(moments[index]?.time || '', x, 1290); context.textAlign = 'left';
    });
    context.fillStyle = '#d3dbea'; context.font = '430 31px system-ui, -apple-system, "Segoe UI", sans-serif';
    drawWrappedText(context, model.explanation, margin, 1400, contentWidth, 43, 2);
    drawVisibilityPill(context, model.visibility, margin, 1515);
    context.fillStyle = '#aebbd2'; context.font = '430 27px system-ui, -apple-system, "Segoe UI", sans-serif';
    drawWrappedText(context, `Consejo: ${model.tip}`, margin, 1625, contentWidth, 38, 2);
  }

  function drawStoryVariantB(context, width, margin, contentWidth) {
    context.fillStyle = '#9aa9ca'; context.font = '650 22px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.letterSpacing = '2px'; context.fillText('EL RECORRIDO POR LA SOMBRA', margin, 390); context.letterSpacing = '0px';
    const shadow = context.createRadialGradient(width * .53, 760, 60, width * .53, 760, 470);
    shadow.addColorStop(0, 'rgba(7, 3, 8, .72)'); shadow.addColorStop(.45, 'rgba(34, 21, 45, .42)'); shadow.addColorStop(.76, 'rgba(90, 77, 121, .12)'); shadow.addColorStop(1, 'rgba(8, 13, 28, 0)');
    context.fillStyle = shadow; context.beginPath(); context.ellipse(width * .53, 760, 470, 330, -.08, 0, Math.PI * 2); context.fill();
    context.strokeStyle = 'rgba(173, 190, 231, .18)'; context.lineWidth = 3; context.setLineDash([10, 14]); context.beginPath(); context.moveTo(110, 825); context.bezierCurveTo(360, 650, 700, 650, 970, 820); context.stroke(); context.setLineDash([]);
    context.fillStyle = 'rgba(178, 188, 218, .48)'; context.font = '500 21px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.fillText('penumbra', 165, 555); context.fillText('sombra más profunda', 420, 635);
    const roles = ['start', 'enter', 'maximum', 'exit', 'end'];
    const xs = [145, 335, 540, 745, 935]; const ys = [830, 745, 705, 745, 830];
    roles.forEach((role, index) => drawEclipseVisual(context, xs[index], ys[index], role === 'maximum' ? 270 : (index === 1 || index === 3 ? 175 : 145), role));
    context.textAlign = 'center'; context.fillStyle = '#c8d2e7'; context.font = '600 23px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.fillText('Entra', xs[0], 955); context.fillText('Avanza', xs[1], 955); context.fillStyle = '#f0d59b'; context.fillText('Máximo', xs[2], 955); context.fillStyle = '#c8d2e7'; context.fillText('Sale', xs[4], 955); context.textAlign = 'left';
    context.fillStyle = '#eef2fb'; context.font = '560 35px system-ui, -apple-system, "Segoe UI", sans-serif';
    drawWrappedText(context, 'La Luna cruza la sombra de la Tierra: se oscurece, alcanza su punto máximo y vuelve a salir.', margin, 1080, contentWidth, 48, 3);
    drawVisibilityPill(context, model.visibility, margin, 1245);
    const moments = Array.isArray(model.moments) ? model.moments : [];
    roundedRect(context, margin, 1340, contentWidth, 170, 28); context.fillStyle = 'rgba(132, 151, 205, .075)'; context.fill(); context.strokeStyle = 'rgba(173, 190, 231, .15)'; context.stroke();
    moments.forEach((item, index) => { const x = margin + 42 + index * 300; context.fillStyle = '#96a5c3'; context.font = '550 22px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText(item.label, x, 1400); context.fillStyle = '#f2f5fc'; context.font = '650 32px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText(item.time, x, 1455); });
    context.fillStyle = '#aebbd2'; context.font = '430 27px system-ui, -apple-system, "Segoe UI", sans-serif';
    drawWrappedText(context, `Consejo: ${model.tip}`, margin, 1585, contentWidth, 38, 2);
  }

  function drawStoryVariantC(context, width, margin, contentWidth) {
    drawEclipseVisual(context, width / 2, 700, 760, 'maximum');
    drawVisibilityPill(context, model.visibility, margin, 1070);
    const moments = Array.isArray(model.moments) ? model.moments : [];
    roundedRect(context, margin, 1165, contentWidth, 190, 28); context.fillStyle = 'rgba(132, 151, 205, .075)'; context.fill(); context.strokeStyle = 'rgba(173, 190, 231, .15)'; context.stroke();
    moments.forEach((item, index) => { const x = margin + 42 + index * 300; context.fillStyle = '#96a5c3'; context.font = '550 22px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText(item.label, x, 1230); context.fillStyle = '#f2f5fc'; context.font = '650 34px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText(item.time, x, 1288); });
    roundedRect(context, margin, 1390, contentWidth * .61, 185, 28); context.fillStyle = 'rgba(132, 151, 205, .075)'; context.fill(); context.strokeStyle = 'rgba(173, 190, 231, .15)'; context.stroke();
    context.fillStyle = '#9aa9ca'; context.font = '650 21px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText('QUÉ VAS A VER', margin + 30, 1440);
    context.fillStyle = '#d3dbea'; context.font = '430 27px system-ui, -apple-system, "Segoe UI", sans-serif'; drawWrappedText(context, model.explanation, margin + 30, 1490, contentWidth * .61 - 60, 37, 3);
    const start = Date.parse(moments[0]?.moment || ''); const end = Date.parse(moments[2]?.moment || ''); const minutes = Math.max(0, Math.round((end - start) / 60000));
    const duration = minutes >= 60 ? `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')} min` : `${minutes} min`;
    const dataX = margin + contentWidth * .65;
    roundedRect(context, dataX, 1390, contentWidth * .35, 185, 28); context.fillStyle = 'rgba(211, 179, 111, .09)'; context.fill(); context.strokeStyle = 'rgba(229, 202, 144, .22)'; context.stroke();
    context.fillStyle = '#9aa9ca'; context.font = '650 20px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText('TIEMPO VISIBLE', dataX + 28, 1440);
    context.fillStyle = '#f0d59b'; context.font = '680 40px system-ui, -apple-system, "Segoe UI", sans-serif'; context.fillText(duration, dataX + 28, 1505);
    context.fillStyle = '#aebbd2'; context.font = '430 27px system-ui, -apple-system, "Segoe UI", sans-serif'; drawWrappedText(context, `Consejo: ${model.tip}`, margin, 1650, contentWidth, 38, 2);
  }

  function drawBrand(context, width, y) {
    context.fillStyle = 'rgba(223, 231, 249, .48)'; context.font = '500 23px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.fillText(model.branding.name.toUpperCase(), 74, y);
    context.textAlign = 'right'; context.fillText(model.branding.domain, width - 74, y); context.textAlign = 'left';
  }

  function draw(formatName) {
    const format = formats[formatName] || formats.story; const { width, height } = format;
    canvas.width = width; canvas.height = height;
    const context = canvas.getContext('2d'); if (!context) return;
    drawBackground(context, width, height);
    const compact = height < 1600; const margin = 74; const contentWidth = width - margin * 2;

    const isEclipse = model.kind === 'lunar_eclipse' || model.kind === 'solar_eclipse';
    context.fillStyle = '#9aa9ca'; context.font = '650 23px system-ui, -apple-system, "Segoe UI", sans-serif';
    context.letterSpacing = '3px'; context.fillText(isEclipse ? 'ECLIPSE DESDE TU UBICACIÓN' : 'ENCUENTRO EN EL CIELO', margin, compact ? 86 : 108); context.letterSpacing = '0px';
    const titleY = compact ? 155 : 190;
    const titleSize = fitFont(context, model.title, contentWidth, compact ? 72 : 82, 56, 680);
    context.font = `680 ${titleSize}px system-ui, -apple-system, "Segoe UI", sans-serif`; context.fillStyle = '#f3f6ff';
    const titleBottom = drawWrappedText(context, model.title, margin, titleY, contentWidth, titleSize * 1.08, 2);
    context.font = `430 ${compact ? 29 : 32}px system-ui, -apple-system, "Segoe UI", sans-serif`; context.fillStyle = '#c1cbe0';
    context.fillText(`${model.date}  ·  ${model.city}`, margin, titleBottom + (compact ? 12 : 18));

    if (isEclipse && !compact && ['a', 'b', 'c'].includes(model.variant)) {
      if (model.variant === 'a') drawStoryVariantA(context, width, margin, contentWidth);
      if (model.variant === 'b') drawStoryVariantB(context, width, margin, contentWidth);
      if (model.variant === 'c') drawStoryVariantC(context, width, margin, contentWidth);
      drawBrand(context, width, height - 65);
      return;
    }

    if (!isEclipse && !compact && ['b', 'c'].includes(model.variant)) {
      drawConjunctionStoryRealistic(context, width, margin, contentWidth, model.variant === 'c');
      drawBrand(context, width, height - 65);
      return;
    }

    const sceneTop = compact ? 325 : 410; const sceneHeight = compact ? 475 : 700;
    const sceneGlow = context.createRadialGradient(width * .48, sceneTop + sceneHeight * .48, 0, width * .48, sceneTop + sceneHeight * .48, 430);
    sceneGlow.addColorStop(0, 'rgba(95, 114, 177, .14)'); sceneGlow.addColorStop(1, 'rgba(5, 10, 24, 0)');
    context.fillStyle = sceneGlow; context.fillRect(0, sceneTop - 80, width, sceneHeight + 160);
    if (isEclipse) {
      drawEclipseVisual(context, width * .5, sceneTop + sceneHeight * .5, compact ? 500 : 680);
    } else {
      const moonRadius = compact ? 172 : 215;
      drawRealMoon(context, width * .39, sceneTop + sceneHeight * .51, moonRadius);
      const planetRadius = model.visual.planet === 'saturn' ? moonRadius * .18 : moonRadius * .13;
      drawPlanet(context, model.visual.planet, width * .73, sceneTop + sceneHeight * .34, planetRadius);
      context.font = `600 ${compact ? 25 : 29}px system-ui, -apple-system, "Segoe UI", sans-serif`; context.fillStyle = '#dbe4f8'; context.textAlign = 'center';
      context.fillText(model.visual.planet_name, width * .73, sceneTop + sceneHeight * .34 + planetRadius + 48); context.textAlign = 'left';
    }

    const copyTop = sceneTop + sceneHeight + (compact ? 22 : 35);
    context.font = `430 ${compact ? 29 : 34}px system-ui, -apple-system, "Segoe UI", sans-serif`; context.fillStyle = '#d3dbea';
    const explanationBottom = drawWrappedText(context, model.explanation, margin, copyTop, contentWidth, compact ? 40 : 47, 2);
    const boxY = explanationBottom + (compact ? 18 : 30); const boxHeight = compact ? 166 : 205;
    roundedRect(context, margin, boxY, contentWidth, boxHeight, 28); context.fillStyle = 'rgba(132, 151, 205, .09)'; context.fill();
    context.strokeStyle = 'rgba(173, 190, 231, .16)'; context.lineWidth = 1.5; context.stroke();
    if (isEclipse) {
      context.fillStyle = '#f0d59b'; context.font = `650 ${compact ? 31 : 37}px system-ui, -apple-system, "Segoe UI", sans-serif`;
      context.fillText(model.visibility, margin + 34, boxY + (compact ? 51 : 61));
      const moments = Array.isArray(model.moments) ? model.moments : [];
      const columnWidth = (contentWidth - 68) / Math.max(1, moments.length);
      moments.forEach((item, index) => {
        const x = margin + 34 + columnWidth * index;
        context.fillStyle = '#96a5c3'; context.font = `550 ${compact ? 21 : 24}px system-ui, -apple-system, "Segoe UI", sans-serif`;
        context.fillText(String(item.label || ''), x, boxY + (compact ? 92 : 111));
        context.fillStyle = '#edf2ff'; context.font = `650 ${compact ? 29 : 34}px system-ui, -apple-system, "Segoe UI", sans-serif`;
        context.fillText(String(item.time || ''), x, boxY + (compact ? 130 : 158));
      });
    } else {
      context.fillStyle = '#f0d59b'; context.font = `650 ${compact ? 37 : 43}px system-ui, -apple-system, "Segoe UI", sans-serif`;
      context.fillText(model.recommended_time, margin + 34, boxY + (compact ? 61 : 74));
      context.fillStyle = '#aebbd2'; context.font = `500 ${compact ? 27 : 31}px system-ui, -apple-system, "Segoe UI", sans-serif`;
      context.fillText(model.time_context, margin + 34, boxY + (compact ? 112 : 132));
    }
    context.fillStyle = '#aebbd2'; context.font = `430 ${compact ? 25 : 28}px system-ui, -apple-system, "Segoe UI", sans-serif`;
    drawWrappedText(context, `Consejo: ${model.tip}`, margin, boxY + boxHeight + (compact ? 36 : 45), contentWidth, compact ? 34 : 39, 2);
    drawBrand(context, width, height - (compact ? 47 : 65));
  }

  function selectedFormat() { return root.querySelector('input[name="infographic_format"]:checked')?.value === 'feed' ? 'feed' : 'story'; }
  function fileSlug(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
  function pngFileName() {
    const subject = model.kind === 'lunar_conjunction' ? `luna-${model.visual.planet_name || model.visual.planet}` : model.title;
    return `aquellas-lunas-${fileSlug(subject)}-${model.date_iso || 'evento'}.png`;
  }
  function sharingFilesSupported() {
    if (!(share instanceof HTMLButtonElement) || typeof navigator.share !== 'function' || typeof File !== 'function') return false;
    if (typeof navigator.canShare !== 'function') return true;
    try { return navigator.canShare({ files: [new File([''], 'prueba.png', { type: 'image/png' })] }); } catch (error) { return false; }
  }
  const canShareFiles = sharingFilesSupported();
  if (share instanceof HTMLButtonElement && canShareFiles) {
    share.hidden = false; share.closest('.event-infographic-actions')?.classList.add('event-infographic-actions--share');
  }
  function setActionsReady(ready) {
    renderReady = ready; download.disabled = !ready;
    if (share instanceof HTMLButtonElement && canShareFiles) share.disabled = !ready;
  }
  function preparePng(formatName) {
    const name = formats[formatName] ? formatName : 'story';
    if (pngCache.has(name)) return pngCache.get(name);
    const pending = new Promise((resolve, reject) => {
      draw(name);
      canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('png_unavailable')), 'image/png');
    });
    pngCache.set(name, pending);
    pending.catch(() => pngCache.delete(name));
    return pending;
  }
  function prewarmSelectedPng() { if (renderReady) preparePng(selectedFormat()).catch(() => {}); }
  root.querySelectorAll('input[name="infographic_format"]').forEach((input) => input.addEventListener('change', () => {
    draw(selectedFormat()); prewarmSelectedPng();
  }));
  download.addEventListener('click', async () => {
    if (!renderReady) return;
    download.disabled = true; if (status) status.textContent = 'Preparando la imagen…';
    try {
      const formatName = selectedFormat(); const format = formats[formatName]; const blob = await preparePng(formatName);
      const url = URL.createObjectURL(blob); const link = document.createElement('a');
      link.href = url; link.download = pngFileName(); document.body.append(link); link.click(); link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
      if (status) status.textContent = `PNG generado en ${format.width} × ${format.height}.`;
    } catch (error) {
      if (status) status.textContent = 'No se pudo generar el PNG en este navegador.';
    } finally { download.disabled = false; }
  });
  if (share instanceof HTMLButtonElement && canShareFiles) share.addEventListener('click', async () => {
    if (!renderReady) return;
    share.disabled = true; if (status) status.textContent = 'Preparando para compartir…';
    try {
      const blob = await preparePng(selectedFormat());
      const file = new File([blob], pngFileName(), { type: 'image/png' });
      if (typeof navigator.canShare === 'function' && !navigator.canShare({ files: [file] })) {
        share.hidden = true; share.closest('.event-infographic-actions')?.classList.remove('event-infographic-actions--share'); if (status) status.textContent = ''; return;
      }
      await navigator.share({ files: [file], title: model.title });
      if (status) status.textContent = 'Infografía compartida.';
    } catch (error) {
      if (error?.name === 'AbortError') { if (status) status.textContent = ''; }
      else if (status) status.textContent = 'No se pudo compartir. Podés descargar el PNG.';
    } finally { if (!share.hidden) share.disabled = false; }
  });
  draw('story');

  const syncMoonSource = () => {
    if (!(moonSource instanceof HTMLElement)) {
      if (status) status.textContent = 'No se pudo preparar la Luna realista.';
      return;
    }
    if (moonSource.dataset.moonThreeState === 'ready') {
      const rendered = moonSource.querySelector('canvas.moon-three-render__canvas');
      if (rendered instanceof HTMLCanvasElement) {
        moonCanvas = rendered;
        setActionsReady(true);
        if (status) status.textContent = '';
        draw(selectedFormat());
        prewarmSelectedPng();
      }
    } else if (moonSource.dataset.moonThreeState === 'fallback') {
      setActionsReady(false);
      if (status) status.textContent = 'Este navegador no pudo preparar la Luna realista.';
    }
  };
  if (moonSource instanceof HTMLElement) {
    new MutationObserver(syncMoonSource).observe(moonSource, { attributes: true, attributeFilter: ['data-moon-three-state'] });
    syncMoonSource();
  } else if (eclipseSources.length === 0) {
    syncMoonSource();
  }
  if (eclipseSources.length > 0) {
    const expectedRoles = Array.isArray(model.visual?.capture_roles) ? model.visual.capture_roles : ['maximum'];
    const capturePoll = window.setInterval(() => {
      eclipseSources.forEach((source) => {
        source.querySelectorAll('[data-eclipse-capture-image]').forEach((sourceImage) => {
          const role = sourceImage.dataset.eclipseCaptureImage || 'maximum';
          if (eclipseImages.has(role) || !sourceImage.src) return;
          const snapshot = new Image();
          snapshot.addEventListener('load', () => {
            eclipseImages.set(role, snapshot);
            if (expectedRoles.every((expected) => eclipseImages.has(expected))) {
              setActionsReady(true);
              if (status) status.textContent = '';
              draw(selectedFormat());
              prewarmSelectedPng();
              window.clearInterval(capturePoll);
            }
          }, { once: true });
          snapshot.src = sourceImage.src;
        });
      });
    }, 200);
  }
})();
