// import { Scene, HemisphereLight, PerspectiveCamera, WebGLRenderer, AmbientLight, DirectionalLight, Color, TextureLoader, RepeatWrapping, SRGBColorSpace, LinearMipmapLinearFilter, LinearFilter, Box3, Vector3, Vector2, ClampToEdgeWrapping, MirroredRepeatWrapping } from 'three';
// import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
// import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
// import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
// // Add this import at the top of your file
// import WooCommerceConnector from './woocommerce-integration.js';
// Texture calibration settings - SET YOUR DESIRED REAL-WORLD SIZE HERE
const TEXTURE_CONFIG = {
    // Real-world dimensions in centimeters
    widthCM: 9.6,    // Width of texture pattern in cm
    heightCM: 9.6,   // Height of texture pattern in cm
    
    // Your texture image dimensions in pixels
    texturePixelWidth: 1024,
    texturePixelHeight: 1024
};

// Calculate the scale factor for realistic sizing
function calculateTextureScale() {
    // Assuming your 3D model is roughly life-sized (shirt width ~50cm)
    const modelWidthCM = 100; // Approximate shirt width in cm
    const modelWidthIn3D = 2.0; // Approximate model width in 3D units
    
    // Calculate how many texture patterns should fit across the model
    const patternsAcrossModel = modelWidthCM / TEXTURE_CONFIG.widthCM;
    
    // Calculate repeat values
    const repeatX = patternsAcrossModel;
    const repeatY = patternsAcrossModel * (TEXTURE_CONFIG.heightCM / TEXTURE_CONFIG.widthCM);
    
    return { x: repeatX, y: repeatY };
}

// Add this after your existing TEXTURE_CONFIG
const BUTTON_CONFIG = {
    // Button material names for each model
    materialNames: {
        'men1': ['Button 1_2569', 'Button 1_2590', 'Button 1_2611', 'Button 1_2632'],
        'men2': ['Default Button_1571'],
        'men3': ['BODY Button_2627', 'SLP Button_2653'],
        'women1': ['Default Button_1836'],
        'women2': ['Shirt_1829']
    },
    
    // Available button colors
    colors: {
        white: 0xffffff,
        black: 0x000000,
        navy: 0x1a237e,
        brown: 0x8d6e63,
        red: 0xe33232,
        silver: 0xc0c0c0,
        cream: 0xf5f5dc
    },
    
    currentColor: 0xffffff // Default white
};

// Function to apply button color to current model
function applyButtonColor(color = BUTTON_CONFIG.currentColor) {
    if (!shirtModel) return;

    // Get current model type
    const activeModelBtn = document.querySelector('.model-btn.active');
    const modelType = activeModelBtn ? activeModelBtn.dataset.model : 'men1';

    // Get button material names for this model
    const buttonMaterials = BUTTON_CONFIG.materialNames[modelType] || [];

    shirtModel.traverse((child) => {
        if (child.isMesh && child.material) {
            const materialName = child.material.name || '';

            // Check if this material is a button material for current model
            const isButton = buttonMaterials.some(buttonMat =>
                materialName.includes(buttonMat) || materialName === buttonMat
            );

            if (isButton) {
                // Use MeshPhysicalMaterial for more realism
                let newMat;
                if (!(child.material instanceof THREE.MeshPhysicalMaterial)) {
                    newMat = new THREE.MeshPhysicalMaterial();
                    THREE.MeshStandardMaterial.prototype.copy.call(newMat, child.material);
                } else {
                    newMat = child.material;
                }

                // Remove any texture
                newMat.map = null;

                // Slightly off-white for white buttons
                if (color === 0xffffff) {
                    newMat.color.setHex(0xfaf9f6);
                } else {
                    newMat.color.setHex(color);
                }

                // Realistic plastic/pearl button look
                newMat.roughness = 0.2;
                newMat.metalness = 0.4;
                newMat.reflectivity = 0.7;
                newMat.clearcoat = 0.7;
                newMat.clearcoatRoughness = 0.15;
                newMat.sheen = 0.3; // subtle fabric sheen

                // Optionally, add a subtle environment map for reflections
                // if (window.buttonEnvMap) newMat.envMap = window.buttonEnvMap;

                newMat.needsUpdate = true;
                child.material = newMat;
            }
        }
    });
}


        const loadingScreen = document.getElementById('loading-screen');
        document.getElementById('viewer-container').appendChild(loadingScreen);

        let currentRotation = 0;
        let isAnimating = false;
        let isDragging = false;
        let previousMouseY = 0;
        let initialModelPosition;

        const scene = new THREE.Scene();
        scene.background = new THREE.Color('rgb(200, 200, 200)');
        
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ 
            canvas: document.querySelector('#canvas'), 
            antialias: true,
            powerPreference: "high-performance",
            alpha: true,
            preserveDrawingBuffer: true
        });

        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.0; // Reduce from 1.2 to 1.0 for more accurate colors
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        renderer.physicallyCorrectLights = false; // Change to false for better color control

        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        
        function updateRendererSize() {
            const container = document.getElementById('viewer-container');
            const width = container.clientWidth;
            const height = container.clientHeight;
            renderer.setSize(width, height);
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
        }
        
        updateRendererSize();
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.shadowMap.enabled = true;

        // const ambientLight = new AmbientLight(0xffffff, 1);
        // scene.add(ambientLight);

        // const directionalLight = new DirectionalLight(0xffffff, 1);
        // directionalLight.position.set(0, 5, 5);
        // directionalLight.castShadow = true;
        // scene.add(directionalLight);

        // const backLight = new DirectionalLight(0xffffff, 0.7);
        // backLight.position.set(0, 5, -5);
        // scene.add(backLight);

        // const leftLight = new DirectionalLight(0xffffff, 0.7);
        // leftLight.position.set(-5, 5, 0);
        // scene.add(leftLight);

        // const rightLight = new DirectionalLight(0xffffff, 0.7);
        // rightLight.position.set(5, 5, 0);
        // scene.add(rightLight);

        // const frontLight = new DirectionalLight(0xffffff, 1);
        // frontLight.position.set(0, 0, 5); // Positions light in front of the model
        // scene.add(frontLight);

        // const ambientLight = new AmbientLight(0xffffff, 0.9);
        // scene.add(ambientLight);

        // Create main directional light that will follow camera
        // const mainLight = new THREE.DirectionalLight(0xffffff, 1.2);
        // scene.add(mainLight);

        // // Create fill lights for better depth
        // const fillLight1 = new THREE.DirectionalLight(0xffffff, 0.8);
        // const fillLight2 = new THREE.DirectionalLight(0xffffff, 0.8);
        // scene.add(fillLight1);
        // scene.add(fillLight2);

        // Environment lighting setup
        const ambientLight = new THREE.AmbientLight(0xffffff, 1.4); // Soft ambient light
        scene.add(ambientLight);

        // Key light (main directional light)
        const keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
        keyLight.position.set(5, 10, 5);
        keyLight.castShadow = true;
        keyLight.shadow.mapSize.width = 2048;
        keyLight.shadow.mapSize.height = 2048;
        keyLight.shadow.camera.near = 0.5;
        keyLight.shadow.camera.far = 50;
        scene.add(keyLight);

        // Fill light (softer, opposite side)
        const fillLight = new THREE.DirectionalLight(0xffffff, 1.0);
        fillLight.position.set(-5, 5, 2);
        scene.add(fillLight);

        // Rim light (back lighting for depth)
        const rimLight = new THREE.DirectionalLight(0xffffff, 1.0);
        rimLight.position.set(0, 5, -5);
        scene.add(rimLight);

        // Hemisphere light for natural color variation
        const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 0.3);
        scene.add(hemiLight);

        camera.position.set(0, 0, 8);
        camera.lookAt(0, 0, 0);
        const controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.minDistance = 2.5;
        controls.maxDistance = 10;

        controls.enableRotate = true;
        controls.maxPolarAngle = Math.PI / 2;
        controls.minPolarAngle = Math.PI / 2;
        controls.enablePan = false;

        const modelLoader = new THREE.GLTFLoader();
        modelLoader.setPath('/models/');

        const dracoLoader = new THREE.DRACOLoader();
        dracoLoader.setDecoderPath('https://www.gstatic.com/draco/v1/decoders/');
        modelLoader.setDRACOLoader(dracoLoader);
        // const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444);
        // scene.add(hemiLight);

        const textureLoader = new THREE.TextureLoader();
        const loadTexture = (url) => {
            return new Promise((resolve) => {
                const texture = textureLoader.load(
                    url,
                    (tex) => {
                        const scale = calculateTextureScale();
                        tex.wrapS = THREE.MirroredRepeatWrapping;
                        tex.wrapT = THREE.MirroredRepeatWrapping;
                        tex.repeat.set(scale.x, scale.y);
                        // const textureNumber = url.match(/\d+/)[0];
                        // if (textureNumber === '5' || textureNumber === '6') {
                        //     tex.rotation = Math.PI / 2; // 90 degrees
                        //     tex.center.set(0.5, 0.5); // Set rotation center
                        //     tex.repeat.set(0, 20);
                        // }
                        tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
                        tex.colorSpace = THREE.SRGBColorSpace;
                        tex.generateMipmaps = true;
                        tex.minFilter = THREE.LinearMipmapLinearFilter;
                        tex.magFilter = THREE.LinearFilter;
                        tex.flipY = false; 
                        tex.encoding = THREE.sRGBEncoding;
                        resolve(tex);
                    },
                    undefined,
                    () => {
                        resolve(null);
                    }
                );
                return texture;
            });
        };

        const textures = {};
        let texturePromises = []; // Initialize empty, will be populated from WordPress data

        // Function to load textures from WordPress
        function loadTexturesFromWordPress() {
            if (window.wooCommerceConnector && window.wooCommerceConnector.fabricTextures) {
                const total = window.wooCommerceConnector.fabricTextures.length;
                let loaded = 0;
                texturePromises = window.wooCommerceConnector.fabricTextures.map(fabric => {
                    const textureUrl = fabric.image_url || site_url + fabric.file_path;
                    return loadTexture(textureUrl).then(tex => {
                        if (tex) {
                            textures[fabric.id] = tex;
                        }
                        loaded++;
                        setLoadingPercentage((loaded / total) * 100);
                    }).catch(error => {
                        loaded++;
                        setLoadingPercentage((loaded / total) * 100);
                    });
                });
                return Promise.all(texturePromises);
            }
            return Promise.resolve();
        }

        // Make textures globally accessible
        window.textures = textures;
        

        Promise.all(texturePromises).then(() => {
            // // console.log('All textures loaded');
            // console.log('');
        });

        Object.entries(textures).forEach(([key, texture]) => {
            texture.addEventListener('load', () => {
                
            });
            texture.addEventListener('error', () => {
                
            });
        });
        
        // Object.values(textures).forEach(texture => {
        //     texture.wrapS = RepeatWrapping;
        //     texture.wrapT = RepeatWrapping;
        //     texture.repeat.set(10, 10);
        // });

        function hideLoadingScreen() {
            const loadingScreen = document.getElementById('loading-screen');
            loadingScreen.style.opacity = '0';
            setLoadingPercentage(100);
            setTimeout(() => {
                loadingScreen.style.display = 'none';
            }, 500);
        }

        let shirtModel;
        window.shirtModel = null; // Initialize as null

        function loadModel() {
            return new Promise((resolve, reject) => {
                modelLoader.load(
                'men1.glb',
                (gltf) => {
                    setLoadingPercentage(100);
                    shirtModel = gltf.scene;
                    window.shirtModel = shirtModel;
                    shirtModel.position.set(0, 0, 0);
                    shirtModel.rotation.set(0, 0, 0);
                    shirtModel.scale.set(0.07, 0.07, 0.07);
                    const storedTexture = localStorage.getItem('selectedTexture') || 'fabric4';
                    shirtModel.traverse((child) => {
                    if (child.isMesh) {
                        child.material.map = textures[storedTexture];

                        // Remove unwanted maps to prevent old patterns/shininess
                        child.material.normalMap = null;
                        child.material.metalnessMap = null;
                        child.material.roughnessMap = null;
                        child.material.displacementMap = null;

                        // Set material properties to desired values
                        child.material.roughness = 0.8;
                        child.material.metalness = 0.0;
                        child.material.normalScale = new THREE.Vector2(1.0, 1.0);
                        child.material.envMapIntensity = 0.5;

                        // Ensure proper color space
                        if (child.material.map) {
                        child.material.map.encoding = THREE.sRGBEncoding;
                        child.material.map.colorSpace = THREE.SRGBColorSpace;
                        }

                        child.material.needsUpdate = true;
                    }
                    });
                    const box = new THREE.Box3().setFromObject(shirtModel);
                    const center = box.getCenter(new THREE.Vector3());
                    shirtModel.position.sub(center);
                    shirtModel.position.y += 0.7;
                    initialModelPosition = shirtModel.position.clone();
                    scene.add(shirtModel);
                    applyStoredTexture();
                    applyButtonColor(BUTTON_CONFIG.currentColor);
                    setupControlAndColorEvents();
                    setControlsEnabled(true);
                    resolve(); // <-- RESOLVE PROMISE HERE
                },
                (progress) => {
                    // Optionally update percentage here if you want to combine model+texture progress
                    if (progress.lengthComputable) {
                    setLoadingPercentage((progress.loaded / progress.total) * 100);
                    }
                },
                (error) => {
                    setLoadingPercentage(100);
                    // Optionally show error
                    resolve(); // Still resolve to not block UI forever
                }
                );
            });
            }

// Global flag to prevent multiple simultaneous model loads
let isModelLoading = false;

// Function to load a new model with complete scene reset
function loadNewModel(modelName) {
  // Prevent multiple simultaneous loads
  if (isModelLoading) {
    // console.log("Model loading already in progress, ignoring request");
    return;
  }
  isModelLoading = true;
  // Show loading screen
  const loadingScreen = document.getElementById('loading-screen');
  if (loadingScreen) {
    loadingScreen.style.display = 'flex';
    loadingScreen.style.opacity = '1';
  }
  // Cancel any pending model loads
  if (window.currentModelLoader) {
    window.currentModelLoader.abort();
    window.currentModelLoader = null;
  }
  // Find and remove all models from the scene
  const modelsToRemove = [];
  scene.traverse((object) => {
    // Check if this is a model (not a light)
    if (object.type === 'Group' || (object.type === 'Object3D' && object !== scene)) {
      if (!object.isLight && object !== camera) {
        modelsToRemove.push(object);
      }
    }
  });
  // Remove all models
  modelsToRemove.forEach(model => {
    scene.remove(model);
    // Dispose of resources
    model.traverse((child) => {
      if (child.isMesh) {
        if (child.geometry) {
          child.geometry.dispose();
        }
        if (child.material) {
          if (Array.isArray(child.material)) {
            child.material.forEach(material => {
              if (material.map) material.map.dispose();
              if (material.lightMap) material.lightMap.dispose();
              if (material.bumpMap) material.bumpMap.dispose();
              if (material.normalMap) material.normalMap.dispose();
              if (material.specularMap) material.specularMap.dispose();
              if (material.envMap) material.envMap.dispose();
              material.dispose();
            });
          } else {
            if (child.material.map) child.material.map.dispose();
            if (child.material.lightMap) child.material.lightMap.dispose();
            if (child.material.bumpMap) child.material.bumpMap.dispose();
            if (child.material.normalMap) child.material.normalMap.dispose();
            if (child.material.specularMap) child.material.specularMap.dispose();
            if (child.material.envMap) child.material.envMap.dispose();
            child.material.dispose();
          }
        }
      }
    });
  });
  // Clear the shirtModel reference
  shirtModel = null;
  // Force a render to clear the scene
  renderer.render(scene, camera);

  // Now load the new model
  window.currentModelLoader = modelLoader.load(
    `${modelName}.glb`,
    (gltf) => {
      window.currentModelLoader = null;
      shirtModel = gltf.scene;
      window.shirtModel = shirtModel; // Update global reference
      shirtModel.position.set(0, 0, 0);
      shirtModel.rotation.set(0, 0, 0);
      shirtModel.scale.set(0.07, 0.07, 0.07);
      const storedTexture = localStorage.getItem('selectedTexture') || 'fabric1';
      shirtModel.traverse((child) => {
        if (child.isMesh) {
          child.material.map = textures[storedTexture];

          // Remove unwanted maps to prevent old patterns/shininess
          child.material.normalMap = null;
          child.material.metalnessMap = null;
          child.material.roughnessMap = null;
          child.material.displacementMap = null;

          // Set material properties to desired values
          child.material.roughness = 0.8;
          child.material.metalness = 0.0;
          child.material.normalScale = new THREE.Vector2(1.0, 1.0);
          child.material.envMapIntensity = 0.5;

          // Ensure proper color space
          if (child.material.map) {
            child.material.map.encoding = THREE.sRGBEncoding;
            child.material.map.colorSpace = THREE.SRGBColorSpace;
          }

          child.material.needsUpdate = true;
        }
      });
      const box = new THREE.Box3().setFromObject(shirtModel);
      const center = box.getCenter(new THREE.Vector3());
      shirtModel.position.sub(center);
      shirtModel.position.y += 0.7;
      if (modelName === 'women1') {
        shirtModel.position.x -= 0.2;
      }
      if (modelName === 'women2') {
        shirtModel.position.x = 0.3;
      }
      initialModelPosition = shirtModel.position.clone();
      // Add the new model to the scene
      scene.add(shirtModel);
      applyStoredTexture();
      applyButtonColor(BUTTON_CONFIG.currentColor);
      hideLoadingScreen();
      setupControlAndColorEvents();
      setControlsEnabled(true);
      // Reset loading flag
      isModelLoading = false;
    },
    (xhr) => {
      // Progress callback - can be used to update loading progress
      const percentComplete = (xhr.loaded / xhr.total) * 100;
      setLoadingPercentage(percentComplete);
    },
    (error) => {
      window.currentModelLoader = null;
      console.error(`Error loading model ${modelName}:`, error);
      setLoadingPercentage(100);
      hideLoadingScreen();
      // Reset loading flag even on error
      isModelLoading = false;
    }
  );
}

function setupControlAndColorEvents() {
    // Remove previous listeners to avoid duplicates
    document.getElementById('zoomIn').onclick = null;
    document.getElementById('zoomOut').onclick = null;
    document.getElementById('home').onclick = null;
    document.getElementById('rotate').onclick = null;
    document.getElementById('moveUp').onclick = null;
    document.getElementById('moveDown').onclick = null;

    document.getElementById('zoomIn').addEventListener('click', () => {
        // ... your zoomIn code ...
        if(!isAnimating) {
            isAnimating = true;
            const startZ = camera.position.z;
            const targetZ = Math.max(controls.minDistance, startZ - 1);
            const zoomAnimation = {
                duration: 500,
                startTime: Date.now(),
                animate: function() {
                    const elapsed = Date.now() - this.startTime;
                    const progress = Math.min(elapsed / this.duration, 1);
                    const eased = 1 - Math.cos((progress * Math.PI) / 2);
                    camera.position.z = startZ + (targetZ - startZ) * eased;
                    if(progress < 1) {
                        requestAnimationFrame(() => this.animate());
                    } else {
                        isAnimating = false;
                    }
                }
            };
            zoomAnimation.animate();
        }
    });
    document.getElementById('zoomOut').addEventListener('click', () => {
        // ... your zoomOut code ...
        if(!isAnimating) {
            isAnimating = true;
            const startZ = camera.position.z;
            const targetZ = Math.min(controls.maxDistance, startZ + 1);
            const zoomAnimation = {
                duration: 500,
                startTime: Date.now(),
                animate: function() {
                    const elapsed = Date.now() - this.startTime;
                    const progress = Math.min(elapsed / this.duration, 1);
                    const eased = 1 - Math.cos((progress * Math.PI) / 2);
                    camera.position.z = startZ + (targetZ - startZ) * eased;
                    if(progress < 1) {
                        requestAnimationFrame(() => this.animate());
                    } else {
                        isAnimating = false;
                    }
                }
            };
            zoomAnimation.animate();
        }
    });
    document.getElementById('home').addEventListener('click', () => {
        // ... your home code ...
        if(!isAnimating) {
            isAnimating = true;
            const startPos = camera.position.clone();
            const targetPos = new THREE.Vector3(0, 0, 8);
            const startRotation = shirtModel ? shirtModel.rotation.y : 0;
            const homeAnimation = {
                duration: 1000,
                startTime: Date.now(),
                animate: function() {
                    const elapsed = Date.now() - this.startTime;
                    const progress = Math.min(elapsed / this.duration, 1);
                    const eased = 1 - Math.cos((progress * Math.PI) / 2);
                    camera.position.lerpVectors(startPos, targetPos, eased);
                    if(shirtModel) {
                        shirtModel.rotation.y = startRotation * (1 - eased);
                        shirtModel.position.lerp(initialModelPosition, eased);
                    }
                    if(progress < 1) {
                        requestAnimationFrame(() => this.animate());
                    } else {
                        currentRotation = 0;
                        isAnimating = false;
                    }
                }
            };
            homeAnimation.animate();
        }
    });
    document.getElementById('rotate').addEventListener('click', () => {
        // ... your rotate code ...
        if(shirtModel && !isAnimating) {
            isAnimating = true;
            const targetRotation = currentRotation + Math.PI;
            const startRotation = shirtModel.rotation.y;
            const rotateAnimation = {
                duration: 1000,
                startTime: Date.now(),
                animate: function() {
                    const elapsed = Date.now() - this.startTime;
                    const progress = Math.min(elapsed / this.duration, 1);
                    const eased = 1 - Math.cos((progress * Math.PI) / 2);
                    shirtModel.rotation.y = startRotation + (targetRotation - startRotation) * eased;
                    if(progress < 1) {
                        requestAnimationFrame(() => this.animate());
                    } else {
                        currentRotation = targetRotation;
                        isAnimating = false;
                    }
                }
            };
            rotateAnimation.animate();
        }
    });
    document.getElementById('moveUp').addEventListener('click', () => {
        // ... your moveUp code ...
        if(shirtModel && !isAnimating) {
            isAnimating = true;
            const startY = shirtModel.position.y;
            const targetY = startY + 0.5;
            const moveAnimation = {
                duration: 500,
                startTime: Date.now(),
                animate: function() {
                    const elapsed = Date.now() - this.startTime;
                    const progress = Math.min(elapsed / this.duration, 1);
                    const eased = 1 - Math.cos((progress * Math.PI) / 2);
                    shirtModel.position.y = startY + (targetY - startY) * eased;
                    if(progress < 1) {
                        requestAnimationFrame(() => this.animate());
                    } else {
                        isAnimating = false;
                    }
                }
            };
            moveAnimation.animate();
        }
    });
    document.getElementById('moveDown').addEventListener('click', () => {
        // ... your moveDown code ...
        if(shirtModel && !isAnimating) {
            isAnimating = true;
            const startY = shirtModel.position.y;
            const targetY = startY - 0.5;
            const moveAnimation = {
                duration: 500,
                startTime: Date.now(),
                animate: function() {
                    const elapsed = Date.now() - this.startTime;
                    const progress = Math.min(elapsed / this.duration, 1);
                    const eased = 1 - Math.cos((progress * Math.PI) / 2);
                    shirtModel.position.y = startY + (targetY - startY) * eased;
                    if(progress < 1) {
                        requestAnimationFrame(() => this.animate());
                    } else {
                        isAnimating = false;
                    }
                }
            };
            moveAnimation.animate();
        }
    });

    // Button color controls
    document.querySelectorAll('.btn-color').forEach(btn => {
        btn.onclick = null;
        btn.addEventListener('click', () => {
            const colorHex = parseInt(btn.dataset.color);
            BUTTON_CONFIG.currentColor = colorHex;
            document.querySelectorAll('.btn-color').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyButtonColor(colorHex);
        });
    });

}

  
// Function to completely reset the scene
function resetScene() {
    // console.log("Completely resetting scene");
    
    // Store references to lights before removing them
    const lights = [];
    scene.traverse((object) => {
        if (object.isLight) {
            lights.push({
                type: object.type,
                color: object.color.clone(),
                intensity: object.intensity,
                position: object.position.clone(),
                target: object.target ? object.target.position.clone() : null
            });
        }
    });
    
    // Remove all objects
    while(scene.children.length > 0) {
        const object = scene.children[0];
        scene.remove(object);
        
        // Dispose of resources
        if (object.geometry) object.geometry.dispose();
        
        if (object.material) {
            if (Array.isArray(object.material)) {
                object.material.forEach(material => {
                    if (material.map) material.map.dispose();
                    if (material.lightMap) material.lightMap.dispose();
                    if (material.bumpMap) material.bumpMap.dispose();
                    if (material.normalMap) material.normalMap.dispose();
                    if (material.specularMap) material.specularMap.dispose();
                    if (material.envMap) material.envMap.dispose();
                    material.dispose();
                });
            } else {
                if (object.material.map) object.material.map.dispose();
                if (object.material.lightMap) object.material.lightMap.dispose();
                if (object.material.bumpMap) object.material.bumpMap.dispose();
                if (object.material.normalMap) object.material.normalMap.dispose();
                if (object.material.specularMap) object.material.specularMap.dispose();
                if (object.material.envMap) object.material.envMap.dispose();
                object.material.dispose();
            }
        }
    }
    
    // Reset references
    shirtModel = null;
    
    // Restore the original lighting setup
    // Create main directional light that will follow camera
    // const mainLight = new THREE.DirectionalLight(0xffffff, 1.5);
    // scene.add(mainLight);

    // // Create fill lights for better depth
    // const fillLight1 = new THREE.DirectionalLight(0xffffff, 0.8);
    // const fillLight2 = new THREE.DirectionalLight(0xffffff, 0.8);
    // scene.add(fillLight1);
    // scene.add(fillLight2);

    // const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444);
    // scene.add(hemiLight);
    // Environment lighting setup
    const ambientLight = new THREE.AmbientLight(0xffffff, 1.4); // Soft ambient light
    scene.add(ambientLight);

    // Key light (main directional light)
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
    keyLight.position.set(5, 10, 5);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.width = 2048;
    keyLight.shadow.mapSize.height = 2048;
    keyLight.shadow.camera.near = 0.5;
    keyLight.shadow.camera.far = 50;
    scene.add(keyLight);

    // Fill light (softer, opposite side)
    const fillLight = new THREE.DirectionalLight(0xffffff, 1.0);
    fillLight.position.set(-5, 5, 2);
    scene.add(fillLight);

    // Rim light (back lighting for depth)
    const rimLight = new THREE.DirectionalLight(0xffffff, 1.0);
    rimLight.position.set(0, 5, -5);
    scene.add(rimLight);

    // Hemisphere light for natural color variation
    const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 0.3);
    scene.add(hemiLight);
    // Force a render
    renderer.render(scene, camera);
    
    // console.log("Scene reset complete. Children count:", scene.children.length);
}



        document.querySelectorAll('.texture-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const textureName = btn.dataset.texture;
                if (shirtModel && textures[textureName]) {
                    localStorage.setItem('selectedTexture', textureName);
                    // Remove active class from all buttons
                    document.querySelectorAll('.texture-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    btn.classList.add('active');
                    
                    shirtModel.traverse((child) => {
                        if (child.isMesh) {
                            child.material.map = textures[textureName];
                            child.material.roughness = 1.0;
                            child.material.metalness = 0.0;
                            child.material.normalScale = new THREE.Vector2(0.5, 0.5);
                            child.material.needsUpdate = true;
                        }
                    });
                    applyButtonColor(BUTTON_CONFIG.currentColor);
                }
                if (window.innerWidth <= 768) {
                    document.getElementById('texture-sidebar').classList.remove('active');
                }
            });
        });

        // Control button functionality
        renderer.domElement.addEventListener('mousedown', (e) => {
            isDragging = true;
            previousMouseY = e.clientY;
        });
        
        renderer.domElement.addEventListener('mousemove', (e) => {
            if (isDragging && shirtModel) {
                const deltaY = e.clientY - previousMouseY;
                shirtModel.position.y -= deltaY * 0.01; // Adjust sensitivity with multiplier
                previousMouseY = e.clientY;
            }
        });
        
        renderer.domElement.addEventListener('mouseup', () => {
            isDragging = false;
        });
        
        renderer.domElement.addEventListener('mouseleave', () => {
            isDragging = false;
        });

        function applyStoredTexture() {
            const storedTextureId = localStorage.getItem('selectedTexture') || 'fabric1';

            if (!shirtModel) return;

            // Try to get the texture directly
            let textureKey = storedTextureId;

            // If it doesn't exist, try to map it (for API IDs)
            if (!textures[textureKey] && window.wooCommerceConnector) {
                textureKey = window.wooCommerceConnector.mapTextureId(storedTextureId);
            }

            // If we have a valid texture, apply it
            if (textures[textureKey]) {
                // console.log(`Applying texture: ${storedTextureId} (mapped to: ${textureKey})`);
                shirtModel.traverse((child) => {
                    if (child.isMesh) {
                        child.material.map = textures[textureKey];
                        child.material.needsUpdate = true;
                    }
                });

                // Update UI to show selected texture
                document.querySelectorAll('.texture-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.texture === storedTextureId);
                });
            } else {
                console.error("Texture not found:", storedTextureId, "mapped to:", textureKey);
                // console.log("Available textures:", Object.keys(textures));
                
                // Apply a default texture if the stored/default one isn't available
                const fallbackTextureKey = 'fabric4'; // Choose a texture that's likely to exist
                if (textures[fallbackTextureKey]) {
                    // console.log(`Applying fallback texture: ${fallbackTextureKey}`);
                    shirtModel.traverse((child) => {
                        if (child.isMesh) {
                            child.material.map = textures[fallbackTextureKey];
                            child.material.needsUpdate = true;
                        }
                    });
                }
            }
            applyButtonColor(BUTTON_CONFIG.currentColor); // Apply button color after texture
        }

        function animate() {
            requestAnimationFrame(animate);
            
            // Make main light follow camera position
            // scene.traverse((object) => {
            //     if (object.type === 'DirectionalLight' && object !== fillLight1 && object !== fillLight2) {
            //         object.position.copy(camera.position);
            //     }
            // });
            
            // // Position fill lights relative to camera
            // if (fillLight1) fillLight1.position.copy(camera.position).add(new THREE.Vector3(5, 2, 0));
            // if (fillLight2) fillLight2.position.copy(camera.position).add(new THREE.Vector3(-5, 2, 0));
            
            controls.update();
            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', updateRendererSize);

        const menuToggle = document.getElementById('menu-toggle');
        const textureSidebar = document.getElementById('texture-sidebar');

        menuToggle.addEventListener('click', () => {
            textureSidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && 
                !textureSidebar.contains(e.target) && 
                !menuToggle.contains(e.target)) {
                textureSidebar.classList.remove('active');
            }
        });

        function filterTextures() {
            // Use WooCommerceConnector if available
            if (window.wooCommerceConnector) {
                window.wooCommerceConnector.filterTextures();
            } else {
                // Original implementation
                const materialFilter = document.getElementById('material-filter').value;
                const colorFilter = document.getElementById('color-filter').value;
                const styleFilter = document.getElementById('style-filter').value;
            
                document.querySelectorAll('.texture-btn').forEach(btn => {
                    const properties = btn.querySelectorAll('.texture-properties');
                    const material = properties[0].textContent;
                    const color = properties[1].textContent;
                    const style = properties[2].textContent;
            
                    const matchesMaterial = !materialFilter || material === materialFilter;
                    const matchesColor = !colorFilter || color === colorFilter;
                    const matchesStyle = !styleFilter || style === styleFilter;
            
                    if (matchesMaterial && matchesColor && matchesStyle) {
                        btn.style.display = 'flex';
                    } else {
                        btn.style.display = 'none';
                    }
                });
            }
        }
        
        // Add event listeners for filters
        document.getElementById('material-filter').addEventListener('change', filterTextures);
        document.getElementById('color-filter').addEventListener('change', filterTextures);
        document.getElementById('style-filter').addEventListener('change', filterTextures);        

// Save filter values
function saveFilters() {
    // Use WooCommerceConnector if available
    if (window.wooCommerceConnector) {
        window.wooCommerceConnector.saveFilters();
    } else {
        // Original implementation
        const filters = {
            material: document.getElementById('material-filter').value,
            color: document.getElementById('color-filter').value,
            style: document.getElementById('style-filter').value
        };
        localStorage.setItem('textureFilters', JSON.stringify(filters));
    }
}

// Restore filter values
function restoreFilters() {
    // Use WooCommerceConnector if available
    if (window.wooCommerceConnector) {
        window.wooCommerceConnector.restoreFilters();
    } else {
        // Original implementation
        const savedFilters = JSON.parse(localStorage.getItem('textureFilters')) || {};
        
        if (savedFilters.material) {
            document.getElementById('material-filter').value = savedFilters.material;
        }
        if (savedFilters.color) {
            document.getElementById('color-filter').value = savedFilters.color;
        }
        if (savedFilters.style) {
            document.getElementById('style-filter').value = savedFilters.style;
        }
        
        filterTextures(); // Apply the restored filters
    }
}

// Update event listeners
document.getElementById('material-filter').addEventListener('change', () => {
    filterTextures();
    saveFilters();
});
document.getElementById('color-filter').addEventListener('change', () => {
    filterTextures();
    saveFilters();
});
document.getElementById('style-filter').addEventListener('change', () => {
    filterTextures();
    saveFilters();
});

// Call restore on page load
document.addEventListener('DOMContentLoaded', () => {
    restoreFilters();
    applyStoredTexture();
});

// Add to cart functionality
document.getElementById('add-to-cart-btn').addEventListener('click', function() {
    // Show loading state
    this.disabled = true;
    this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Добавяне...';
    
    // Get selected model, texture, and size
    const activeModelBtn = document.querySelector('.model-btn.active');
    const activeTextureBtn = document.querySelector('.texture-btn.active');
    const sizeSelect = document.getElementById('shirt-size');
    const activeButtonColorBtn = document.querySelector('.btn-color.active');
    const buttonColorHex = activeButtonColorBtn ? activeButtonColorBtn.dataset.color : '0xffffff';
    const buttonColorName = getButtonColorName(parseInt(buttonColorHex));
    const modelType = activeModelBtn ? activeModelBtn.dataset.model : 'men1';
    const textureId = activeTextureBtn ? activeTextureBtn.dataset.texture : 'fabric1';
    const size = sizeSelect ? sizeSelect.value : 'M';
    
    // Add to cart using WooCommerce connector
    window.wooCommerceConnector.addToCart(textureId, modelType, size, buttonColorName)
        .then(response => {
            // Show success message
            this.innerHTML = '<i class="fa-solid fa-check"></i> Добавено в количката!';
            
            // Update cart count if available
            const cartCount = document.querySelector('.cart-count');
            if (cartCount && response.cart_count) {
                cartCount.textContent = response.cart_count;
                cartCount.style.display = 'block';
            }
            
            // Redirect to cart or show success message
            setTimeout(() => {
                if (confirm('Продуктът е добавен в количката! Към количката сега?')) {
                    window.location.href = response.cart_url;
                } else {
                    // Reset button
                    this.disabled = false;
                    this.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Добавяне в количката';
                }
            }, 1000);
        })
        .catch(error => {
            // Show error message
            console.error('Грешка при добавяне в количката:', error);
            this.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Грешка';
            
            // Show error message to user
            alert('Грешка при добавяне в количката: ' + error.message);
            
            // Reset button after a delay
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Добавяне в количката';
            }, 2000);
        });
});


// Helper function to get a display name for the model
function getModelDisplayName(modelType) {
    const modelNames = {
        'men1': 'Men\'s Classic Shirt',
        'men2': 'Men\'s Slim Fit Shirt',
        'men3': 'Men\'s Casual Shirt',
        'women1': 'Women\'s Classic Shirt',
        'women2': 'Women\'s Fitted Shirt'
    };
    
    return modelNames[modelType] || 'Custom Shirt';
}


// Define size options for each model type
const sizeOptions = {
    // Men's models
    'men1': [
        { value: 'XS', label: 'XS' },
        { value: 'S', label: 'S' },
        { value: 'M', label: 'M' },
        { value: 'L', label: 'L' },
        { value: 'XL', label: 'XL' },
        { value: 'XXL', label: 'XXL' },
        { value: '3XL', label: '3XL' },
        { value: '4XL', label: '4XL' },
        { value: '5XL', label: '5XL' }
    ],
    'men2': [
        { value: '40', label: '40' },
        { value: '41', label: '41' },
        { value: '42', label: '42' },
        { value: '43', label: '43' },
        { value: '44', label: '44' },
        { value: '45', label: '45' },
        { value: '46', label: '46' },
        { value: '47', label: '47' },
        { value: '48', label: '48' },
        { value: '49', label: '49' },
        { value: '50', label: '50' },
    ],
    'men3': [
        { value: 'XS', label: 'XS' },
        { value: 'S', label: 'S' },
        { value: 'M', label: 'M' },
        { value: 'L', label: 'L' },
        { value: 'XL', label: 'XL' },
        { value: 'XXL', label: 'XXL' },
        { value: '3XL', label: '3XL' },
        { value: '4XL', label: '4XL' },
        { value: '5XL', label: '5XL' }
    ],
    // Women's models
    'women1': [
        { value: '36', label: '36' },
        { value: '38', label: '38' },
        { value: '40', label: '40' },
        { value: '42', label: '42' },
        { value: '44', label: '44' },
        { value: '46', label: '46' },
        { value: '48', label: '48' },
        { value: '50', label: '50' },
        { value: '52', label: '52' },
        { value: '54', label: '54' },
        { value: '56', label: '56' },
        { value: '58', label: '58' }
    ],
    'women2': [
        { value: 'XXS', label: 'XXS' },
        { value: 'XS', label: 'XS' },
        { value: 'S', label: 'S' },
        { value: 'M', label: 'M' },
        { value: 'L', label: 'L' },
        { value: 'XL', label: 'XL' },
        { value: 'XXL', label: 'XXL' },
        { value: '3XL', label: '3XL' },
        { value: '4XL', label: '4XL' },
        { value: '5XL', label: '5XL' },
        { value: '6XL', label: '6XL' }
    ]
};

// Model prices (will be updated from the server)
let modelPrices = {
    'men1': 119.00,
    'men2': 129.00,
    'men3': 139.00,
    'women1': 119.00,
    'women2': 129.00
};

// Model display names
const modelNames = {
    'men1': 'Men\'s Classic Shirt',
    'men2': 'Men\'s Slim Fit Shirt',
    'men3': 'Men\'s Casual Shirt',
    'women1': 'Women\'s Classic Shirt',
    'women2': 'Women\'s Fitted Shirt'
};

// Fetch configuration data from the server
function fetchConfigData() {
    fetch('https://(server-domain)/wp-json/shirt-configurator/v1/get-config', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update model prices if provided
            if (data.model_prices) {
                modelPrices = data.model_prices;
            }
            
            // Update price display
            updatePriceDisplay();
        }
    })
    .catch(error => {
        console.error('Error fetching configuration data:', error);
    });
}

// Call this when the page loads
document.addEventListener('DOMContentLoaded', fetchConfigData);

// Function to update size options based on selected model
function updateSizeOptions(modelType) {
    const sizeSelect = document.getElementById('shirt-size');
    if (!sizeSelect) return;
    
    // Clear existing options
    sizeSelect.innerHTML = '';
    
    // Get options for the selected model
    const options = sizeOptions[modelType] || sizeOptions['men1']; // Default to men1 if model not found
    
    // Add options to select
    options.forEach(option => {
        const optionElement = document.createElement('option');
        optionElement.value = option.value;
        optionElement.textContent = option.label;
        sizeSelect.appendChild(optionElement);
    });
}

// Function to update the price display
function updatePriceDisplay() {
    const priceDisplay = document.getElementById('price-display');
    if (!priceDisplay) return;
    
    const activeModelBtn = document.querySelector('.model-btn.active');
    const modelType = activeModelBtn ? activeModelBtn.dataset.model : 'men1';
    
    // Try to use WooCommerceConnector if available
    if (window.wooCommerceConnector && typeof window.wooCommerceConnector.updatePriceDisplay === 'function') {
        window.wooCommerceConnector.updatePriceDisplay();
    } else {
        // Fallback to using local model prices
        const modelPrices = {
            'men1': 119.00,
            'men2': 129.00,
            'men3': 139.00,
            'women1': 119.00,
            'women2': 129.00
        };
        
        const price = modelPrices[modelType] || 119.00;
        priceDisplay.textContent = price.toFixed(2) + ' лв.';
    }
}

// Initialize size options for the default model on page load
document.addEventListener('DOMContentLoaded', () => {
    import('./woocommerce-integration.js')
        .then(module => {
            window.wooCommerceConnector = module.default;
            // console.log('WooCommerceConnector loaded successfully');
        })
        .catch(error => {
            console.error('Error loading WooCommerceConnector:', error);
        });

    const activeModelBtn = document.querySelector('.model-btn.active');
    const initialModel = activeModelBtn ? activeModelBtn.dataset.model : 'men1';
    
    // Create size selector if it doesn't exist
    if (!document.getElementById('shirt-size-container')) {
        createSizeSelector();
    }
    
    // Create price display if it doesn't exist
    if (!document.getElementById('price-display-container')) {
        createPriceDisplay();
    }
    
    // // Create quantity selector if it doesn't exist
    // if (!document.getElementById('quantity-selector-container')) {
    //     createQuantitySelector();
    // }
    
    // Create add to cart button if it doesn't exist
    if (!document.getElementById('add-to-cart-btn')) {
        createAddToCartButton();
    }
    
    // Apply stored filters and textures
    restoreFilters();
    applyStoredTexture();
});


// Function to create the size selector
function createSizeSelector() {
    const container = document.createElement('div');
    container.id = 'shirt-size-container';
    container.className = 'size-selector';
    
    const label = document.createElement('label');
    label.htmlFor = 'shirt-size';
    label.textContent = 'Size:';
    
    const select = document.createElement('select');
    select.id = 'shirt-size';
    
    container.appendChild(label);
    container.appendChild(select);
    
    // Add to the viewer container
    const viewerContainer = document.getElementById('viewer-container');
    if (viewerContainer) {
        viewerContainer.appendChild(container);
    } else {
        document.body.appendChild(container);
    }
}

// Function to create the price display
function createPriceDisplay() {
    const container = document.createElement('div');
    container.id = 'price-display-container';
    container.className = 'price-display';
    
    const label = document.createElement('span');
    label.className = 'price-label';
    label.textContent = 'Price: ';
    
    const price = document.createElement('span');
    price.id = 'price-display';
    price.className = 'price-value';
    price.textContent = '119.00 лв.';
    
    container.appendChild(label);
    container.appendChild(price);
    
    // Add to the viewer container
    const viewerContainer = document.getElementById('viewer-container');
    if (viewerContainer) {
        viewerContainer.appendChild(container);
    } else {
        document.body.appendChild(container);
    }
}

// Add event listeners for quantity buttons
document.addEventListener('DOMContentLoaded', function() {
    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');
    const quantityInput = document.getElementById('shirt-quantity');
    
    if (minusBtn && plusBtn && quantityInput) {
        minusBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
        
        plusBtn.addEventListener('click', function() {
            let currentValue = parseInt(quantityInput.value);
            quantityInput.value = currentValue + 1;
        });
        
        // Ensure the input only accepts numbers
        quantityInput.addEventListener('input', function() {
            let value = parseInt(quantityInput.value);
            if (isNaN(value) || value < 1) {
                quantityInput.value = 1;
            }
        });
    }
});

// Function to create the add to cart button
function createAddToCartButton() {
    const button = document.createElement('button');
    button.id = 'add-to-cart-btn';
    button.className = 'add-to-cart-btn';
    button.innerHTML = '<i class="fa-solid fa-cart-plus"></i> Add to Cart';
    
    // Add to the viewer container
    const viewerContainer = document.getElementById('viewer-container');
    if (viewerContainer) {
        viewerContainer.appendChild(button);
    } else {
        document.body.appendChild(button);
    }
    
    // Add event listener
    button.addEventListener('click', addToCart);
}

// Add event listeners to model buttons
document.querySelectorAll('.model-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        // Prevent default action and stop propagation
        e.preventDefault();
        e.stopPropagation();
        
        // Check if button is already active
        if (this.classList.contains('active')) {
            // console.log("Button already active, ignoring click");
            return;
        }
        
        // console.log("Model button clicked:", this.dataset.model);
        
        // First, update the active state of buttons
        document.querySelectorAll('.model-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Get the model name from the data attribute
        const modelName = this.dataset.model;
        
        // Show loading screen
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.style.display = 'flex';
            loadingScreen.style.opacity = '1';
        }
        
        // Reset the scene completely
        resetScene();
        
        // Load the new model
        loadNewModel(modelName);
        
        // Update size options using WooCommerceConnector
        if (window.wooCommerceConnector) {
            window.wooCommerceConnector.updateSizeOptions(modelName);
            window.wooCommerceConnector.updatePriceDisplay();
        } else {
            // Fallback to the original function
            updateSizeOptions(modelName);
            updatePriceDisplay();
        }
    });
});


// Function to add the configured shirt to cart
// Function to add the configured shirt to cart
function addToCart() {
    // Show loading state
    const button = document.getElementById('add-to-cart-btn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Добавяне...';
    button.disabled = true;
    
    // Get selected options
    const activeModelBtn = document.querySelector('.model-btn.active');
    const modelType = activeModelBtn ? activeModelBtn.dataset.model : 'men1';
    const selectedTexture = localStorage.getItem('selectedTexture') || 'fabric1';
    const selectedSize = document.getElementById('shirt-size').value;
    const buttonColorHex = activeButtonColorBtn ? activeButtonColorBtn.dataset.color : '0xffffff';
    const buttonColorName = getButtonColorName(parseInt(buttonColorHex));
    
    // Use WooCommerceConnector if available
    if (window.wooCommerceConnector) {
        window.wooCommerceConnector.addToCart(selectedTexture, modelType, selectedSize, buttonColorName)
            .then(data => {
                button.innerHTML = '<i class="fa-solid fa-check"></i> Added!';
                
                // Show success message
                showMessage('Shirt added to cart!', 'success');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error';
                
                // Show error message
                showMessage('Could not add to cart. Please try again.', 'error');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            });
    } else {
        // Get quantity
        const quantityInput = document.getElementById('shirt-quantity');
        const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
        
        // Fallback to the original implementation
        fetch(`${window.location.origin}/wp-admin/admin-ajax.php`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                'action': 'add_shirt_to_cart',
                'model': modelType,
                'texture': selectedTexture,
                'size': selectedSize,
                'quantity': quantity,
                'button_color': buttonColorName
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                button.innerHTML = '<i class="fa-solid fa-check"></i> Added!';
                
                // Show success message
                showMessage('Shirt added to cart!', 'success');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            } else {
                throw new Error(data.message || 'Грешка при добавяне в количката');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error';
            
            // Show error message
            showMessage('Could not add to cart. Please try again.', 'error');
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        });
    }
}


// Function to show messages
function showMessage(message, type = 'info') {
    // Create message container if it doesn't exist
    let messageContainer = document.getElementById('message-container');
    
    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.id = 'message-container';
        document.body.appendChild(messageContainer);
    }
    
    // Create message element
    const messageElement = document.createElement('div');
    messageElement.className = `message ${type}`;
    messageElement.textContent = message;
    
    // Add close button
    const closeButton = document.createElement('span');
    closeButton.className = 'close-message';
    closeButton.innerHTML = '&times;';
    closeButton.onclick = function() {
        messageElement.remove();
    };
    
    messageElement.appendChild(closeButton);
    messageContainer.appendChild(messageElement);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        messageElement.remove();
    }, 5000);
}
function showLoadingScreen() {
    const loadingScreen = document.getElementById('loading-screen');
    if (loadingScreen) {
        loadingScreen.style.display = 'flex';
        loadingScreen.style.opacity = '1';
    }
    setLoadingPercentage(0);
}
async function loadEverythingAndShow() {
    showLoadingScreen();
    // Wait for WooCommerceConnector to be ready
    if (window.wooCommerceConnector && window.wooCommerceConnector.fetchWooCommerceData) {
        await new Promise(resolve => {
            if (window.wooCommerceConnector.isReady) return resolve();
            const checkReady = () => {
                if (window.wooCommerceConnector.isReady) resolve();
                else setTimeout(checkReady, 50);
            };
            checkReady();
        });
    }

    // Load textures from WordPress data
    await loadTexturesFromWordPress();
    await loadModel();
    // Hide loading screen
    hideLoadingScreen();
}

document.addEventListener('DOMContentLoaded', () => {
    loadEverythingAndShow();
});

// Helper function to get button color name in Bulgarian
function getButtonColorName(colorHex) {
    const colorNames = {
        0xffffff: 'Бели',
        0x000000: 'Черни',
        0x1a237e: 'Тъмносини',
        0x8d6e63: 'Кафяви',
        0xe33232: 'Червени',
        0xc0c0c0: 'Сребърни',
        0xf5f5dc: 'Кремави'
    };
    
    return colorNames[colorHex] || 'Бели';
}

function setControlsEnabled(enabled) {
    document.querySelectorAll('.control-btn, .btn-color').forEach(btn => {
        btn.disabled = !enabled;
        if (!enabled) {
            btn.classList.add('disabled');
        } else {
            btn.classList.remove('disabled');
        }
    });
}
setControlsEnabled(false);

function setLoadingPercentage(percent) {
    const el = document.getElementById('loader-percentage');
    if (el) el.textContent = `${Math.round(percent)}%`;
}

// --- Advanced Try On Feature with BodyPix & Pose Estimation ---
document.addEventListener('DOMContentLoaded', function() {
    const tryonBtn = document.getElementById('tryon-btn');
    const tryonInput = document.getElementById('tryon-photo-input');
    const tryonModal = document.getElementById('tryon-modal');
    const tryonClose = document.getElementById('tryon-close');
    const tryonCanvas = document.getElementById('tryon-canvas');

    if (!tryonBtn || !tryonInput || !tryonModal || !tryonClose || !tryonCanvas) return;

    let bodyPixNet = null;
    let poseDetector = null;

    async function loadBodyPix() {
        if (!bodyPixNet) {
            bodyPixNet = await bodyPix.load();
        }
        return bodyPixNet;
    }

    async function loadPoseDetector() {
        if (!poseDetector) {
            poseDetector = await poseDetection.createDetector(
                poseDetection.SupportedModels.MoveNet,
                { modelType: poseDetection.movenet.modelType.SINGLEPOSE_LIGHTNING }
            );
        }
        return poseDetector;
    }

    tryonBtn.addEventListener('click', () => tryonInput.click());

    tryonInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            showTryonModal(ev.target.result);
        };
        reader.readAsDataURL(file);
    });

    tryonClose.addEventListener('click', () => {
        tryonModal.style.display = 'none';
    });

    async function showTryonModal(userImgSrc) {
        // --- 1. Render shirt with transparent background ---
        const prevBg = scene.background;
        const prevClearAlpha = renderer.getClearAlpha ? renderer.getClearAlpha() : 1;

        // Hide background meshes if any (optional, depends on your model)
        let hiddenMeshes = [];
        if (window.shirtModel) {
            window.shirtModel.traverse(child => {
                if (child.isMesh && child.material && child.material.name.toLowerCase().includes('background')) {
                    if (child.visible) hiddenMeshes.push(child);
                    child.visible = false;
                }
            });
        }

        scene.background = null;
        renderer.setClearColor(0x000000, 0);
        renderer.render(scene, camera);
        const shirtImgSrc = renderer.domElement.toDataURL('image/png');
        // Restore background
        scene.background = prevBg;
        renderer.setClearColor(0xd7d7d7, prevClearAlpha);

        // Restore hidden meshes
        hiddenMeshes.forEach(mesh => mesh.visible = true);

        // --- 2. Load both images ---
        const userImg = new Image();
        const shirtImg = new Image();

        userImg.onload = async function() {
            tryonCanvas.width = userImg.width;
            tryonCanvas.height = userImg.height;
            const ctx = tryonCanvas.getContext('2d');
            ctx.clearRect(0, 0, tryonCanvas.width, tryonCanvas.height);

            // --- 3. Run BodyPix segmentation ---
            const net = await loadBodyPix();
            const offCanvas = document.createElement('canvas');
            offCanvas.width = userImg.width;
            offCanvas.height = userImg.height;
            offCanvas.getContext('2d').drawImage(userImg, 0, 0);

            const segmentation = await net.segmentPerson(offCanvas, {
                internalResolution: 'medium',
                segmentationThreshold: 0.85
            });

            // --- 4. Run pose estimation ---
            const detector = await loadPoseDetector();
            const poses = await detector.estimatePoses(offCanvas);
            let keypoints = null;
            if (poses && poses.length > 0) {
                keypoints = poses[0].keypoints;
            }

            // --- 5. Prepare shirt overlay transformation ---
            shirtImg.onload = function() {
                // Default shirt placement
                let scale = 0.5;
                let shirtW = shirtImg.width * scale;
                let shirtH = shirtImg.height * scale;
                let x = (userImg.width - shirtW) / 2;
                let y = (userImg.height - shirtH) / 2;
                let angle = 0;

                // If pose detected, align shirt to shoulders
                if (keypoints) {
                    const leftShoulder = keypoints.find(k => k.name === 'left_shoulder' || k.part === 'leftShoulder');
                    const rightShoulder = keypoints.find(k => k.name === 'right_shoulder' || k.part === 'rightShoulder');
                    const left = leftShoulder || keypoints[5];
                    const right = rightShoulder || keypoints[6];

                    if (left && right && left.score > 0.3 && right.score > 0.3) {
                        const centerX = (left.x + right.x) / 2;
                        const centerY = (left.y + right.y) / 2;
                        const shoulderDist = Math.hypot(left.x - right.x, left.y - right.y);

                        // Scale shirt width to match shoulder distance (shirtW = 2.2x shoulder width)
                        scale = (shoulderDist * 2.2) / shirtImg.width;
                        shirtW = shirtImg.width * scale;
                        shirtH = shirtImg.height * scale;

                        // Place shirt so its collar is just below the shoulder center
                        x = centerX - shirtW / 2;
                        y = centerY + shirtH * 0.05; // Move shirt lower (adjust 0.05 as needed)
                        angle = Math.atan2(right.y - left.y, right.x - left.x);
                    }
                }

                // --- 6. Draw shirt behind, with rotation and vertical flip ---
                ctx.save();
                ctx.translate(x + shirtW / 2, y + shirtH * 0.18);
                ctx.rotate(angle);
                ctx.translate(-shirtW / 2, -shirtH * 0.18 + shirtH);
                ctx.scale(1, -1);
                ctx.globalAlpha = 0.92;
                ctx.drawImage(shirtImg, 0, 0, shirtW, shirtH);
                ctx.globalAlpha = 1.0;
                ctx.restore();

                // --- 7. Draw user photo foreground (using mask as alpha) ---
                const mask = bodyPix.toMask(
                    segmentation,
                    {r:0, g:0, b:0, a:0},     // background: transparent
                    {r:255, g:255, b:255, a:255} // person: opaque white
                );

                // Create a canvas from the ImageData mask
                const maskCanvas = document.createElement('canvas');
                maskCanvas.width = userImg.width;
                maskCanvas.height = userImg.height;
                maskCanvas.getContext('2d').putImageData(mask, 0, 0);

                const maskedUserCanvas = document.createElement('canvas');
                maskedUserCanvas.width = userImg.width;
                maskedUserCanvas.height = userImg.height;
                const maskedCtx = maskedUserCanvas.getContext('2d');

                // Draw the user image
                maskedCtx.drawImage(userImg, 0, 0);

                // Use the mask as an alpha mask
                maskedCtx.globalCompositeOperation = 'destination-in';
                maskedCtx.drawImage(maskCanvas, 0, 0);

                // Draw the masked user image on the main canvas (on top of shirt)
                ctx.drawImage(maskedUserCanvas, 0, 0);

                tryonModal.style.display = 'flex';
            };
            shirtImg.src = shirtImgSrc;
        };
        userImg.src = userImgSrc;
    }
});
