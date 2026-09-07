import * as THREE from './vendor/three.module.min.js';

const root = document.querySelector('[data-satellite-stations]');
if (root) {
  const loadingNotice = root.querySelector('[data-stations-loading]');
  window.addEventListener('error', (event) => {
    if (!loadingNotice?.isConnected) return;
    loadingNotice.textContent = 'No se pudo iniciar el visor 3D.';
    loadingNotice.title = event.message || 'Error de inicialización';
    root.classList.add('has-error');
  }, {once: true});
  try {
  const payload = JSON.parse(root.querySelector('[data-stations-payload]').textContent || '{}');
  const earthRadius = Number(payload.earth_radius_km);
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(38, 1, .01, 40);
  const renderer = new THREE.WebGLRenderer({antialias: true, powerPreference: 'high-performance'});
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.setClearColor(0x01040b, 1);
  renderer.domElement.className = 'stations-canvas'; root.prepend(renderer.domElement);

  const textureLoader = new THREE.TextureLoader();
  const textureUrl = (name) => new URL(`../images/earth/${name}-4k.webp`, import.meta.url).href;
  const configureEarthTexture = (texture, color = true) => { texture.colorSpace = color ? THREE.SRGBColorSpace : THREE.NoColorSpace; texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy()); };
  const loadEarthTexture = (name, onLoad, color = true) => textureLoader.load(textureUrl(name), (texture) => { configureEarthTexture(texture, color); onLoad(texture); });
  const earthGeometry = new THREE.SphereGeometry(1, 96, 64);
  const blackPixel = new THREE.DataTexture(new Uint8Array([2, 5, 10, 255]), 1, 1); blackPixel.needsUpdate = true;
  const earthUniforms = {dayMap: {value: blackPixel}, nightMap: {value: blackPixel}, heightMap: {value: blackPixel}, sunDirection: {value: new THREE.Vector3(1, 0, 0)}, fullyLit: {value: 0}, bumpStrength: {value: .16}};
  const earthMaterial = new THREE.ShaderMaterial({uniforms: earthUniforms, vertexShader: `
    varying vec2 vUv; varying vec3 vNormalWorld; varying vec3 vPositionWorld;
    void main(){
      vUv=uv;
      vNormalWorld=normalize(mat3(modelMatrix)*normal);
      vPositionWorld=(modelMatrix*vec4(position,1.0)).xyz;
      gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.0);
    }`, fragmentShader: `
    uniform sampler2D dayMap; uniform sampler2D nightMap; uniform sampler2D heightMap;
    uniform vec3 sunDirection; uniform float fullyLit; uniform float bumpStrength;
    varying vec2 vUv; varying vec3 vNormalWorld; varying vec3 vPositionWorld;
    vec3 reliefNormal(vec3 surfaceNormal){
      vec3 sigmaX=dFdx(vPositionWorld); vec3 sigmaY=dFdy(vPositionWorld);
      float height=texture2D(heightMap,vUv).r;
      vec2 heightGradient=vec2(dFdx(height),dFdy(height));
      vec3 r1=cross(sigmaY,surfaceNormal); vec3 r2=cross(surfaceNormal,sigmaX);
      float determinant=dot(sigmaX,r1);
      vec3 gradient=sign(determinant)*(heightGradient.x*r1+heightGradient.y*r2);
      return normalize(abs(determinant)*surfaceNormal-gradient*bumpStrength);
    }
    void main(){
      vec3 baseNormal=normalize(vNormalWorld); vec3 detailedNormal=reliefNormal(baseNormal);
      vec3 dayColor=texture2D(dayMap,vUv).rgb; vec3 nightColor=texture2D(nightMap,vUv).rgb;
      vec3 lightDirection=normalize(sunDirection);
      float geometricSolarDot=dot(baseNormal,lightDirection);
      float reliefSolarDot=dot(detailedNormal,lightDirection);
      float dayAmount=smoothstep(-.1,.14,geometricSolarDot);
      vec3 brightDay=pow(dayColor,vec3(.82))*1.18;
      float daylight=.94+.18*max(geometricSolarDot,0.0);
      float localRelief=clamp(1.0+(reliefSolarDot-geometricSolarDot)*.38,.9,1.12);
      vec3 realColor=mix(nightColor*1.13,brightDay*daylight*localRelief,dayAmount);
      float softVolume=.94+.06*max(dot(detailedNormal,normalize(cameraPosition-vPositionWorld)),0.0);
      vec3 fullColor=dayColor*softVolume;
      gl_FragColor=vec4(mix(realColor,fullColor,fullyLit),1.0);
      #include <tonemapping_fragment>
      #include <colorspace_fragment>
    }`});
  const earth = new THREE.Mesh(earthGeometry, earthMaterial);
  scene.add(earth);
  loadEarthTexture('blue-marble', (texture) => { earthUniforms.dayMap.value = texture; });
  loadEarthTexture('black-marble', (texture) => { earthUniforms.nightMap.value = texture; });
  loadEarthTexture('topography', (texture) => { earthUniforms.heightMap.value = texture; }, false);

  const ecefVector = (value, scale = 1 / earthRadius) => new THREE.Vector3(Number(value.x) * scale, Number(value.z) * scale, -Number(value.y) * scale);
  const surfacePoint = (lat, lon, radius = 1.015) => {
    const phi = THREE.MathUtils.degToRad(lat), lambda = THREE.MathUtils.degToRad(lon);
    return new THREE.Vector3(radius * Math.cos(phi) * Math.cos(lambda), radius * Math.sin(phi), -radius * Math.cos(phi) * Math.sin(lambda));
  };
  const observerMarkerCanvas = document.createElement('canvas'); observerMarkerCanvas.width = 64; observerMarkerCanvas.height = 64;
  const observerMarkerContext = observerMarkerCanvas.getContext('2d');
  observerMarkerContext.beginPath(); observerMarkerContext.arc(32, 32, 20, 0, Math.PI * 2);
  observerMarkerContext.fillStyle = '#78efb6'; observerMarkerContext.fill();
  observerMarkerContext.lineWidth = 4; observerMarkerContext.strokeStyle = 'rgba(225,255,242,.9)'; observerMarkerContext.stroke();
  const observerMarkerTexture = new THREE.CanvasTexture(observerMarkerCanvas);
  const observer = new THREE.Sprite(new THREE.SpriteMaterial({map: observerMarkerTexture, transparent: true, depthTest: true, depthWrite: false, sizeAttenuation: true}));
  observer.scale.set(.01, .01, 1);
  observer.position.copy(surfacePoint(payload.observer.latitude, payload.observer.longitude, 1.006)); scene.add(observer);

  const objects = {};
  Object.entries(payload.satellites).forEach(([id, satellite]) => {
    const color = new THREE.Color(satellite.color); const group = new THREE.Group();
    const marker = new THREE.Mesh(new THREE.OctahedronGeometry(.045, 1), new THREE.MeshBasicMaterial({color}));
    const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints(satellite.samples.map((sample) => ecefVector(sample.ecef_km))), new THREE.LineBasicMaterial({color, transparent: true, opacity: .72}));
    group.add(marker, line); scene.add(group); objects[id] = {group, marker, line, samples: satellite.samples, times: satellite.samples.map((sample) => Date.parse(sample.datetime))};
  });

  const bands = [];
  payload.transit_bands.forEach((band) => {
    const vertices = []; const indices = []; const steps = 60; const halfLength = 44;
    for (let index = 0; index <= steps; index += 1) {
      const longitude = Number(band.center_longitude) - halfLength + 2 * halfLength * index / steps;
      const lower = surfacePoint(Number(band.center_latitude) - Number(band.half_width_degrees), longitude, 1.008);
      const upper = surfacePoint(Number(band.center_latitude) + Number(band.half_width_degrees), longitude, 1.008);
      vertices.push(lower.x, lower.y, lower.z, upper.x, upper.y, upper.z);
      if (index < steps) indices.push(index * 2, index * 2 + 1, index * 2 + 2, index * 2 + 1, index * 2 + 3, index * 2 + 2);
    }
    const geometry = new THREE.BufferGeometry(); geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3)); geometry.setIndex(indices);
    const mesh = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({color: 0xb895ff, transparent: true, opacity: .25, side: THREE.DoubleSide, depthWrite: false}));
    mesh.userData.maximum = Date.parse(band.maximum_utc); scene.add(mesh); bands.push(mesh);
  });

  const interpolate = (samples, times, timestamp, key) => {
    if (timestamp <= times[0]) return samples[0][key]; if (timestamp >= times.at(-1)) return samples.at(-1)[key];
    let low = 0; let high = times.length - 1;
    while (high - low > 1) { const middle = (low + high) >> 1; if (times[middle] <= timestamp) low = middle; else high = middle; }
    const fraction = (timestamp - times[low]) / (times[high] - times[low]); const a = samples[low][key]; const b = samples[high][key];
    return {x: a.x + (b.x - a.x) * fraction, y: a.y + (b.y - a.y) * fraction, z: a.z + (b.z - a.z) * fraction};
  };
  const moonTimes = payload.moon.map((sample) => Date.parse(sample.datetime));
  const hasSolarSeries = Array.isArray(payload.sun) && payload.sun.length > 1;
  const sunSamples = hasSolarSeries ? payload.sun : payload.moon.map((sample) => ({datetime: sample.datetime, direction_ecef: {x: 1, y: 0, z: 0}}));
  const sunTimes = sunSamples.map((sample) => Date.parse(sample.datetime));
  let timestamp = Date.parse(payload.instant); let playing = payload.start_paused !== true; let previous = performance.now(); let yaw = .55; let pitch = .25; let distance = 4.1; let downFov = 105; let dragging = false; let lastPointer = null;
  const selection = document.querySelector('[data-stations-selection]'); const view = document.querySelector('[data-stations-view]');
  const orientationControl = document.querySelector('[data-stations-orientation-control]'); const orientation = document.querySelector('[data-stations-orientation]');
  const lighting = document.querySelector('[data-stations-lighting]');
  const orbits = document.querySelector('[data-stations-orbits]');
  const play = document.querySelector('[data-stations-play]'); const speed = document.querySelector('[data-stations-speed]'); const clock = document.querySelector('[data-stations-clock]'); const help = root.querySelector('[data-stations-help]');
  const updateCamera = (moonDirection) => {
    const stationView = view.value === 'iss' || view.value === 'tiangong'; orientationControl.hidden = !stationView;
    if (view.value === 'moon') { camera.fov = 38; camera.position.copy(moonDirection).multiplyScalar(4.2); camera.up.set(0, 1, 0); camera.lookAt(0, 0, 0); help.textContent = 'Vista alineada con la dirección Tierra–Luna'; }
    else if (view.value === 'iss' || view.value === 'tiangong') {
      const stationPosition = objects[view.value].marker.position; const radial = stationPosition.clone().normalize(); const nadir = radial.clone().negate();
      camera.position.copy(stationPosition); camera.fov = orientation.value === 'down' ? downFov : 58;
      if (orientation.value === 'down') { camera.near = .001; camera.up.set(0, 1, 0); camera.lookAt(0, 0, 0); help.textContent = 'Hacia abajo · rueda para ajustar el encuadre'; }
      else {
        let tangent = new THREE.Vector3(0, 1, 0).addScaledVector(radial, -radial.y);
        if (tangent.lengthSq() < .001) tangent = new THREE.Vector3(1, 0, 0).addScaledVector(radial, -radial.x);
        tangent.normalize(); const sight = tangent.multiplyScalar(.92).addScaledVector(nadir, .38).normalize(); camera.up.copy(radial); camera.lookAt(camera.position.clone().add(sight)); help.textContent = 'Horizonte · curvatura terrestre y espacio';
      }
    }
    else { camera.fov = 38; camera.near = .01; camera.up.set(0, 1, 0); camera.position.set(distance * Math.cos(pitch) * Math.sin(yaw), distance * Math.sin(pitch), distance * Math.cos(pitch) * Math.cos(yaw)); camera.lookAt(0, 0, 0); help.textContent = 'Arrastrá para rotar · rueda para acercar'; }
    if (view.value !== 'free' && !(stationView && orientation.value === 'down')) camera.near = .01;
    camera.updateProjectionMatrix();
  };
  const updateMarkerScales = () => {
    const perspective = Math.tan(THREE.MathUtils.degToRad(camera.fov * .5));
    Object.values(objects).forEach((object) => {
      const cameraDistance = camera.position.distanceTo(object.marker.position);
      object.marker.visible = cameraDistance > .025;
      const compensatedScale = THREE.MathUtils.clamp(.009 * cameraDistance * perspective / .045, .12, 8);
      object.marker.scale.setScalar(compensatedScale);
    });
    const observerDistance = camera.position.distanceTo(observer.position);
    const observerScale = Math.max(.0001, .03 * observerDistance * perspective);
    observer.scale.set(observerScale, observerScale, 1);
  };
  function render(now) {
    const elapsed = Math.min(100, now - previous); previous = now;
    if (playing) timestamp += elapsed * Number(speed.value);
    const start = Date.parse(payload.range.start), end = Date.parse(payload.range.end); if (timestamp > end) timestamp = start; if (timestamp < start) timestamp = start;
    Object.entries(objects).forEach(([id, object]) => {
      object.group.visible = selection.value === 'both' || selection.value === id;
      object.line.visible = orbits.value === 'show';
      object.marker.position.copy(ecefVector(interpolate(object.samples, object.times, timestamp, 'ecef_km')));
    });
    const moonDirection = ecefVector(interpolate(payload.moon, moonTimes, timestamp, 'direction_ecef'), 1).normalize();
    const sunDirection = ecefVector(interpolate(sunSamples, sunTimes, timestamp, 'direction_ecef'), 1).normalize(); earthUniforms.sunDirection.value.copy(sunDirection); earthUniforms.fullyLit.value = lighting.value === 'full' ? 1 : 0;
    bands.forEach((band) => { band.visible = Math.abs(timestamp - band.userData.maximum) < 45 * 60 * 1000; });
    updateCamera(moonDirection); updateMarkerScales(); clock.dateTime = new Date(timestamp).toISOString(); clock.textContent = new Intl.DateTimeFormat('es-AR', {dateStyle: 'short', timeStyle: 'medium', timeZone: payload.timezone}).format(new Date(timestamp));
    renderer.render(scene, camera); requestAnimationFrame(render);
  }
  const resize = () => { const width = root.clientWidth, height = root.clientHeight; renderer.setSize(width, height, false); camera.aspect = width / Math.max(1, height); camera.updateProjectionMatrix(); };
  new ResizeObserver(resize).observe(root); resize();
  play.addEventListener('click', () => { playing = !playing; play.textContent = playing ? 'Pausa' : 'Reproducir'; play.setAttribute('aria-pressed', String(!playing)); });
  document.querySelector('[data-stations-now]').addEventListener('click', () => {
    const currentTimestamp = globalThis.siteTimeContext?.simulated ? Date.parse(globalThis.siteTimeContext.now) : Date.now();
    const rangeStart = Date.parse(payload.range.start); const rangeEnd = Date.parse(payload.range.end);
    if (Number.isFinite(currentTimestamp) && currentTimestamp >= rangeStart && currentTimestamp <= rangeEnd) {
      timestamp = currentTimestamp;
      return;
    }
    const currentUrl = new URL(globalThis.location.href);
    currentUrl.searchParams.delete('time');
    if (selection.value === 'both') currentUrl.searchParams.delete('station');
    else currentUrl.searchParams.set('station', selection.value);
    globalThis.location.assign(currentUrl.href);
  });
  renderer.domElement.addEventListener('pointerdown', (event) => { if (view.value !== 'free') return; dragging = true; lastPointer = event; renderer.domElement.setPointerCapture(event.pointerId); });
  renderer.domElement.addEventListener('pointermove', (event) => { if (!dragging || !lastPointer) return; yaw -= (event.clientX - lastPointer.clientX) * .006; pitch = THREE.MathUtils.clamp(pitch + (event.clientY - lastPointer.clientY) * .006, -1.35, 1.35); lastPointer = event; });
  renderer.domElement.addEventListener('pointerup', () => { dragging = false; lastPointer = null; });
  renderer.domElement.addEventListener('wheel', (event) => {
    if (view.value === 'free') { event.preventDefault(); distance = THREE.MathUtils.clamp(distance + event.deltaY * .003, 2.1, 8); return; }
    if ((view.value === 'iss' || view.value === 'tiangong') && orientation.value === 'down') { event.preventDefault(); downFov = THREE.MathUtils.clamp(downFov + event.deltaY * .035, 65, 125); }
  }, {passive: false});
  loadingNotice?.remove(); requestAnimationFrame(render);
  } catch (error) {
    console.error('Aquellas Lunas stations viewer initialization failed:', error);
    if (loadingNotice) {
      loadingNotice.textContent = `No se pudo iniciar el visor 3D: ${error instanceof Error ? error.message : String(error)}`;
      loadingNotice.title = '';
    }
    root.classList.add('has-error');
  }
}
