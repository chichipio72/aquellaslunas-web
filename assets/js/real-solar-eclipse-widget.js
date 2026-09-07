import { clamp, interpolate, publishEclipseCapture, setupTimeline, unwrapAngularSamples } from './real-eclipse-controls.js?v=20260827-infographic-capture';

const smoothstep = (start, end, value) => {
  const progress = clamp((value - start) / (end - start), 0, 1);
  return progress * progress * (3 - 2 * progress);
};

const circleOverlap = (sunRadius, moonRadius, separation) => {
  if (separation >= sunRadius + moonRadius) return 0;
  if (separation <= Math.abs(sunRadius - moonRadius)) return moonRadius >= sunRadius ? 1 : moonRadius ** 2 / sunRadius ** 2;
  const sunAngle = Math.acos(clamp((separation ** 2 + sunRadius ** 2 - moonRadius ** 2) / (2 * separation * sunRadius), -1, 1));
  const moonAngle = Math.acos(clamp((separation ** 2 + moonRadius ** 2 - sunRadius ** 2) / (2 * separation * moonRadius), -1, 1));
  const triangle = 0.5 * Math.sqrt(Math.max(0, (-separation + sunRadius + moonRadius) * (separation + sunRadius - moonRadius) * (separation - sunRadius + moonRadius) * (separation + sunRadius + moonRadius)));
  return (sunRadius ** 2 * sunAngle + moonRadius ** 2 * moonAngle - triangle) / (Math.PI * sunRadius ** 2);
};

const seededNoise = (index, salt = 0) => {
  const value = Math.sin((index + 1) * 12.9898 + salt * 78.233) * 43758.5453;
  return value - Math.floor(value);
};

const colorChannels = (color) => [1, 3, 5].map((offset) => Number.parseInt(color.slice(offset, offset + 2), 16));
const rgba = (color, alpha) => { const [red, green, blue] = colorChannels(color); return `rgba(${red},${green},${blue},${clamp(alpha, 0, 1)})`; };
const scaledColor = (color, intensity) => { const channels = colorChannels(color).map((channel) => Math.round(clamp(channel * intensity, 0, 255))); return `rgb(${channels.join(',')})`; };

const totalityEffectReveal = (time, c2, c3, beforeMilliseconds, afterMilliseconds) => {
  if (!Number.isFinite(c2) || !Number.isFinite(c3)) return 0;
  if (time >= c2 && time <= c3) return 1;
  if (time < c2 && beforeMilliseconds > 0 && time >= c2 - beforeMilliseconds) return smoothstep(c2 - beforeMilliseconds, c2, time);
  if (time > c3 && afterMilliseconds > 0 && time <= c3 + afterMilliseconds) return 1 - smoothstep(c3, c3 + afterMilliseconds, time);
  return 0;
};

const contactEffectReveal = (time, c2, c3, beforeMilliseconds, afterMilliseconds) => {
  const insideMilliseconds = 5000;
  if (!Number.isFinite(c2) || !Number.isFinite(c3)) return 0;
  if (time < c2) return beforeMilliseconds > 0 && time >= c2 - beforeMilliseconds ? smoothstep(c2 - beforeMilliseconds, c2, time) : 0;
  if (time <= c2 + insideMilliseconds) return 1 - smoothstep(c2, c2 + insideMilliseconds, time);
  if (time >= c3 - insideMilliseconds && time <= c3) return smoothstep(c3 - insideMilliseconds, c3, time);
  if (time > c3) return afterMilliseconds > 0 && time <= c3 + afterMilliseconds ? 1 - smoothstep(c3, c3 + afterMilliseconds, time) : 0;
  return 0;
};

function paintCorona(context, radius, level, reveal, intensity, color) {
  if (level <= 0 || reveal <= 0) return;
  const strength = level / 10;
  context.save();
  context.globalCompositeOperation = 'screen';

  const outerExtent = radius * (1.55 + strength * 1.05);
  const halo = context.createRadialGradient(0, 0, radius * 0.88, 0, 0, outerExtent);
  halo.addColorStop(0, rgba(color, 0.22 * reveal * strength * intensity));
  halo.addColorStop(0.08, rgba(color, 0.12 * reveal * strength * intensity));
  halo.addColorStop(0.38, rgba(color, 0.035 * reveal * strength * intensity));
  halo.addColorStop(1, rgba(color, 0));
  context.fillStyle = halo;
  context.beginPath(); context.arc(0, 0, outerExtent, 0, Math.PI * 2); context.fill();

  const filamentCount = Math.round(28 + level * 11);
  for (let index = 0; index < filamentCount; index += 1) {
    const angle = index / filamentCount * Math.PI * 2 + (seededNoise(index, 1) - 0.5) * 0.22;
    const streamerBias = 0.55 + 0.45 * Math.abs(Math.cos(angle - 0.42));
    const length = radius * (0.35 + strength * (0.45 + 1.05 * seededNoise(index, 2)) * streamerBias);
    const bend = (seededNoise(index, 3) - 0.5) * radius * (0.22 + strength * 0.28);
    const startRadius = radius * (0.92 + seededNoise(index, 4) * 0.1);
    const endRadius = startRadius + length;
    const tangentX = -Math.sin(angle); const tangentY = Math.cos(angle);
    const startX = Math.cos(angle) * startRadius; const startY = Math.sin(angle) * startRadius;
    const endAngle = angle + (seededNoise(index, 5) - 0.5) * 0.2;
    const endX = Math.cos(endAngle) * endRadius; const endY = Math.sin(endAngle) * endRadius;
    const gradient = context.createLinearGradient(startX, startY, endX, endY);
    const alpha = reveal * strength * (0.018 + seededNoise(index, 6) * 0.035);
    gradient.addColorStop(0, rgba(color, alpha * 2.2 * intensity));
    gradient.addColorStop(0.42, rgba(color, alpha * intensity));
    gradient.addColorStop(1, rgba(color, 0));
    context.strokeStyle = gradient;
    context.lineWidth = radius * (0.003 + seededNoise(index, 7) * 0.012);
    context.lineCap = 'round';
    context.beginPath();
    context.moveTo(startX, startY);
    context.bezierCurveTo(
      Math.cos(angle) * (startRadius + length * 0.28) + tangentX * bend,
      Math.sin(angle) * (startRadius + length * 0.28) + tangentY * bend,
      Math.cos(endAngle) * (startRadius + length * 0.72) - tangentX * bend * 0.28,
      Math.sin(endAngle) * (startRadius + length * 0.72) - tangentY * bend * 0.28,
      endX, endY,
    );
    context.stroke();
  }
  context.restore();
}

function paintFlatSun(context, radius, color, intensity, limbDarkening) {
  context.fillStyle = scaledColor(color, intensity);
  context.beginPath(); context.arc(0, 0, radius, 0, Math.PI * 2); context.fill();
  context.save(); context.beginPath(); context.arc(0, 0, radius, 0, Math.PI * 2); context.clip();
  const limb = context.createRadialGradient(0, 0, radius * 0.76, 0, 0, radius);
  limb.addColorStop(0, 'rgba(70,48,24,0)');
  limb.addColorStop(0.9, 'rgba(70,48,24,0.008)');
  limb.addColorStop(1, `rgba(70,48,24,${limbDarkening})`);
  context.fillStyle = limb; context.fillRect(-radius, -radius, radius * 2, radius * 2); context.restore();
}

function paintProminences(context, radius, moonX, moonY, moonRadius, level, reveal, intensity, color) {
  if (level <= 0 || reveal <= 0) return;
  const count = Math.round(1 + level * 0.7);
  context.save(); context.globalCompositeOperation = 'screen'; context.lineCap = 'round';
  for (let index = 0; index < count; index += 1) {
    const angle = 0.28 + index * 2.399 + (seededNoise(index, 11) - 0.5) * 0.42;
    const anchorX = Math.cos(angle) * radius; const anchorY = Math.sin(angle) * radius;
    const coveredByMoon = Math.hypot(anchorX - moonX, anchorY - moonY) <= moonRadius * 1.012;
    if (!coveredByMoon) continue;
    const height = radius * (0.018 + seededNoise(index, 12) * 0.035) * (0.45 + level / 10);
    const width = height * (0.55 + seededNoise(index, 13) * 0.45);
    const normalX = Math.cos(angle); const normalY = Math.sin(angle); const tangentX = -normalY; const tangentY = normalX;
    context.strokeStyle = rgba(color, reveal * (0.22 + level * 0.035) * intensity);
    context.lineWidth = Math.max(0.7, radius * 0.0035);
    context.beginPath();
    context.moveTo(anchorX - tangentX * width, anchorY - tangentY * width);
    context.bezierCurveTo(anchorX + normalX * height + tangentX * width, anchorY + normalY * height + tangentY * width, anchorX + normalX * height - tangentX * width, anchorY + normalY * height - tangentY * width, anchorX + tangentX * width, anchorY + tangentY * width);
    context.stroke();
  }
  context.restore();
}

function paintBailyAndDiamond(context, radius, moonX, moonY, moonRadius, level, proximity, intensity, color) {
  if (level <= 0 || proximity <= 0) return;
  const direction = Math.atan2(-moonY, -moonX);
  const count = Math.round(2 + level * 0.8);
  context.save(); context.globalCompositeOperation = 'screen'; context.fillStyle = rgba(color, intensity);
  for (let index = 0; index < count; index += 1) {
    const offset = (index - (count - 1) / 2) * (0.012 + 0.002 * (10 - level));
    const angle = direction + offset;
    const size = radius * (0.0025 + level * 0.0005) * proximity * (0.55 + seededNoise(index, 21));
    context.shadowColor = color; context.shadowBlur = size * (2 + level * 0.4) * intensity;
    context.beginPath(); context.arc(Math.cos(angle) * radius, Math.sin(angle) * radius, size, 0, Math.PI * 2); context.fill();
  }
  const diamondSize = radius * (0.006 + level * 0.0012) * proximity ** 2;
  context.shadowBlur = diamondSize * 7; context.beginPath(); context.arc(Math.cos(direction) * radius, Math.sin(direction) * radius, diamondSize, 0, Math.PI * 2); context.fill();
  context.restore();
}

document.querySelectorAll('[data-real-solar-eclipse]').forEach((root) => {
  const payload = JSON.parse(root.querySelector('[data-real-eclipse-payload]').textContent);
  const eclipse = payload.eclipse; const canvas = root.querySelector('canvas'); const context = canvas.getContext('2d');
  const samples = unwrapAngularSamples(eclipse.samples);
  const resize = () => { const bounds = canvas.getBoundingClientRect(); const density = Math.min(devicePixelRatio || 1, 2); canvas.width = Math.round(bounds.width * density); canvas.height = Math.round(bounds.height * density); };
  new ResizeObserver(resize).observe(canvas); resize();
  setupTimeline(root, payload, (progress, time) => {
    const sample = interpolate(samples, progress); const width = canvas.width; const height = canvas.height; const radius = Math.min(width, height) * 0.25;
    const moonX = sample.moon_x_solar_radii * radius; const moonY = -sample.moon_y_solar_radii * radius; const moonRadius = sample.moon_radius_solar_radii * radius;
    const separation = Math.hypot(sample.moon_x_solar_radii, sample.moon_y_solar_radii); const covered = circleOverlap(1, sample.moon_radius_solar_radii, separation);
    const exposure = Number(eclipse.animation.exposure); const darkness = Number(eclipse.animation.sky_darkening) * covered ** 3;
    const skyFactor = Number(eclipse.animation.sky_brightness) * exposure * (1 - darkness); const skyChannels = colorChannels(eclipse.animation.sky_color).map((channel) => Math.round(clamp(channel * skyFactor, 0, 255)));
    const background = context.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, Math.max(width, height) * 0.75);
    background.addColorStop(0, `rgb(${skyChannels.join(',')})`); background.addColorStop(1, scaledColor('#01030a', Math.max(0.35, skyFactor)));
    context.fillStyle = background; context.fillRect(0, 0, width, height);
    context.save(); context.translate(width / 2, height / 2); context.rotate(-sample.celestial_north_screen_angle * Math.PI / 180);
    const totalEvent = eclipse.local_classification === 'total';
    const c2 = eclipse.contacts.C2 ? Date.parse(eclipse.contacts.C2) : NaN; const c3 = eclipse.contacts.C3 ? Date.parse(eclipse.contacts.C3) : NaN;
    const beforeMilliseconds = Number(eclipse.animation.totality_effects_before_seconds) * 1000; const afterMilliseconds = Number(eclipse.animation.totality_effects_after_seconds) * 1000;
    const coronaReveal = totalEvent ? totalityEffectReveal(time, c2, c3, beforeMilliseconds, afterMilliseconds) : 0;
    paintCorona(context, radius, Number(eclipse.animation.corona_level), coronaReveal, Number(eclipse.animation.corona_intensity) * exposure, eclipse.animation.corona_color);
    paintFlatSun(context, radius, eclipse.animation.sun_color, Number(eclipse.animation.sun_intensity) * exposure, Number(eclipse.animation.limb_darkening));
    const contactProximity = totalEvent ? contactEffectReveal(time, c2, c3, beforeMilliseconds, afterMilliseconds) : 0;
    paintProminences(context, radius, moonX, moonY, moonRadius, Number(eclipse.animation.prominence_level), coronaReveal, Number(eclipse.animation.prominence_intensity) * exposure, eclipse.animation.prominence_color);
    context.fillStyle = eclipse.animation.moon_color; context.beginPath(); context.arc(moonX, moonY, moonRadius, 0, Math.PI * 2); context.fill();
    paintBailyAndDiamond(context, radius, moonX, moonY, moonRadius, Number(eclipse.animation.baily_level), contactProximity, Number(eclipse.animation.baily_intensity) * exposure, eclipse.animation.baily_color);
    context.restore();
  });
  root.classList.add('is-ready');
  publishEclipseCapture(canvas, 'solar', root.closest('[data-eclipse-capture]') !== null, eclipse.capture_instants && Object.keys(eclipse.capture_instants).length > 0);
});
