class WooCommerceConnector {
    constructor() {
        this.siteUrl = window.location.origin; // Dynamically get the site URL
        this.nonce = '';
        this.productId = null;
        this.variationId = null;
        this.modelPrices = {
            'men1': 119.00,
            'men2': 129.00,
            'men3': 139.00,
            'women1': 119.00,
            'women2': 129.00
        };
        // New properties to store data from WordPress
        this.availableModels = [];
        this.modelSizes = {};
        this.fabricTextures = [];
        this.init();
    }

    init() {
        // Get WooCommerce data from WordPress
        this.fetchWooCommerceData();
    }

    fetchWooCommerceData() {
    // Fetch necessary data from WordPress (nonce, product IDs, models, sizes, fabrics)
    fetch(`${this.siteUrl}/wp-json/shirt-configurator/v1/init`, {
        credentials: 'include',
        method: 'GET',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Network response was not ok: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // console.log('Received data from API:', data); // Add this for debugging
        
        if (data.success) {
            this.nonce = data.nonce;
            this.productId = data.productId;
            
            // Store model prices if provided
            if (data.model_prices) {
                this.modelPrices = data.model_prices;
            }
            
            // Store models data if provided
            if (data.models) {
                // Check if models is an array or an object
                if (Array.isArray(data.models)) {
                    this.availableModels = data.models;
                } else if (typeof data.models === 'object') {
                    // Convert object to array if needed
                    this.availableModels = Object.keys(data.models).map(key => {
                        return {
                            id: key,
                            name: data.models[key].name || key,
                            image_url: data.models[key].thumbnail || ''
                        };
                    });
                } else {
                    console.error('Models data is not in expected format:', data.models);
                    this.availableModels = [];
                }
            }
            
            // Store sizes per model if provided
            if (data.model_sizes) {
                this.modelSizes = data.model_sizes;
            }
            
            // Store fabric textures if provided
            if (data.fabrics) {
                this.fabricTextures = data.fabrics;
            } else if (data.textures) {
                // Handle textures in a different format
                this.fabricTextures = Object.keys(data.textures).map(key => {
                    return {
                        id: key,
                        name: data.textures[key].name || key,
                        image_url: data.textures[key].file || '',
                        file_path: data.textures[key].file || '',
                        properties: {
                            material: data.textures[key].material || '',
                            color: data.textures[key].color || '',
                            style: data.textures[key].style || ''
                        }
                    };
                });
            }
            if (window.loadTexturesFromWordPress) {
                window.loadTexturesFromWordPress();
            }
            // console.log('WooCommerce connection initialized successfully');
            // console.log('Models:', this.availableModels);
            // console.log('Textures:', this.fabricTextures);
            
            // Update UI with the fetched data
            this.updateUI();
            
            
            // Update price display immediately
            this.updatePriceDisplay();
            this.isReady = true;
        } else {
            console.error('Error in init data:', data);
        }
    })
    .catch(error => {
        console.error('Error initializing WooCommerce connection:', error);
        // Try again after a delay
        setTimeout(() => this.fetchWooCommerceData(), 5000);
    });
}


    // New method to update the UI with WordPress data
    updateUI() {
        // Update models
        this.updateModelsUI();
        
        // Update fabric textures
        this.updateFabricsUI();
        
        // Only generate texture buttons if textures are loaded
        if (window.textures) {
            this.generateTextureButtons();
        } else {
            console.warn("Waiting for textures to load before generating buttons...");
            // Try again after a short delay
            setTimeout(() => {
                if (window.textures) {
                    this.generateTextureButtons();
                } else {
                    console.error("Textures not loaded after delay");
                }
            }, 1000);
        }
        
        // Update size options for the current model
        this.updateSizeOptions();
    }

    // Update the models UI
    updateModelsUI() {
    const modelButtonsContainer = document.querySelector('.model-buttons');
    if (!modelButtonsContainer) return;

    // Clear existing buttons
    modelButtonsContainer.innerHTML = '';

    // Generate a button for each available model
    this.availableModels.forEach((model, idx) => {
        const btn = document.createElement('button');
        btn.className = 'model-btn';
        btn.dataset.model = model.id;
        if (idx === 0) btn.classList.add('active');
        btn.title = model.name || '';

        // Add image if available
        if (model.image_url) {
            const img = document.createElement('img');
            img.src = model.image_url;
            img.alt = model.name || '';
            img.width = 24;
            img.height = 24;
            btn.appendChild(img);
        } else {
            btn.textContent = model.name || model.id;
        }

        // Add click event (copy your existing logic)
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (this.classList.contains('active')) return;
            document.querySelectorAll('.model-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const modelName = this.dataset.model;
            const loadingScreen = document.getElementById('loading-screen');
            if (loadingScreen) {
                loadingScreen.style.display = 'flex';
                loadingScreen.style.opacity = '1';
            }
            resetScene();
            loadNewModel(modelName);
            if (window.wooCommerceConnector) {
                window.wooCommerceConnector.updateSizeOptions(modelName);
                window.wooCommerceConnector.updatePriceDisplay();
            } else {
                updateSizeOptions(modelName);
                updatePriceDisplay();
            }
        });

        modelButtonsContainer.appendChild(btn);
    });
}


    // Update the fabrics UI
    updateFabricsUI() {
        if (!this.fabricTextures || this.fabricTextures.length === 0) return;
        
        const textureContent = document.getElementById('texture-content');
        if (!textureContent) return;
        
        // Update existing texture buttons
        this.fabricTextures.forEach(fabric => {
            const fabricBtn = document.querySelector(`.texture-btn[data-texture="${fabric.id}"]`);
            if (fabricBtn) {
                // Update fabric information
                const nameEl = fabricBtn.querySelector('.texture-name');
                if (nameEl && fabric.name) nameEl.textContent = fabric.name;
                
                const priceEl = fabricBtn.querySelector('.texture-price');
                if (priceEl && fabric.price) priceEl.textContent = `${fabric.price.toFixed(2)} лв.`;
                
                // Update properties
                const propertiesEls = fabricBtn.querySelectorAll('.texture-properties');
                if (propertiesEls.length > 0 && fabric.properties) {
                    // Update material
                    if (propertiesEls[0] && fabric.properties.material) 
                        propertiesEls[0].textContent = fabric.properties.material;
                    
                    // Update color
                    if (propertiesEls[1] && fabric.properties.color) 
                        propertiesEls[1].textContent = fabric.properties.color;
                    
                    // Update style
                    if (propertiesEls[2] && fabric.properties.style) 
                        propertiesEls[2].textContent = fabric.properties.style;
                }
            }
        });
        
        // Update filter options
        this.updateFilterOptions();
    }

    // Update filter options based on available fabrics
    updateFilterOptions() {
        if (!this.fabricTextures || this.fabricTextures.length === 0) return;
        
        // Get unique materials, colors, and styles
        const materials = new Set();
        const colors = new Set();
        const styles = new Set();
        
        this.fabricTextures.forEach(fabric => {
            if (fabric.properties) {
                if (fabric.properties.material) materials.add(fabric.properties.material);
                if (fabric.properties.color) colors.add(fabric.properties.color);
                if (fabric.properties.style) styles.add(fabric.properties.style);
            }
        });
        
        // Update material filter
        const materialFilter = document.getElementById('material-filter');
        if (materialFilter) {
            // Keep the first option (empty value)
            const firstOption = materialFilter.options[0];
            materialFilter.innerHTML = '';
            materialFilter.appendChild(firstOption);
            
            // Add options from the data
            materials.forEach(material => {
                const option = document.createElement('option');
                option.value = material;
                option.textContent = material;
                materialFilter.appendChild(option);
            });
        }
        
        // Update color filter
        const colorFilter = document.getElementById('color-filter');
        if (colorFilter) {
            // Keep the first option (empty value)
            const firstOption = colorFilter.options[0];
            colorFilter.innerHTML = '';
            colorFilter.appendChild(firstOption);
            
            // Add options from the data
            colors.forEach(color => {
                const option = document.createElement('option');
                option.value = color;
                option.textContent = color;
                colorFilter.appendChild(option);
            });
        }
        
        // Update style filter
        const styleFilter = document.getElementById('style-filter');
        if (styleFilter) {
            // Keep the first option (empty value)
            const firstOption = styleFilter.options[0];
            styleFilter.innerHTML = '';
            styleFilter.appendChild(firstOption);
            
            // Add options from the data
            styles.forEach(style => {
                const option = document.createElement('option');
                option.value = style;
                option.textContent = style;
                styleFilter.appendChild(option);
            });
        }
        
        // Restore any saved filters
        this.restoreFilters();
    }

    // Restore saved filters
    restoreFilters() {
        const savedFilters = JSON.parse(localStorage.getItem('textureFilters')) || {};
        
        if (savedFilters.material) {
            const materialFilter = document.getElementById('material-filter');
            if (materialFilter) materialFilter.value = savedFilters.material;
        }
        
        if (savedFilters.color) {
            const colorFilter = document.getElementById('color-filter');
            if (colorFilter) colorFilter.value = savedFilters.color;
        }
        
        if (savedFilters.style) {
            const styleFilter = document.getElementById('style-filter');
            if (styleFilter) styleFilter.value = savedFilters.style;
        }
        
        // Apply the filters
        this.filterTextures();
    }

    // Filter textures based on selected filters
    filterTextures() {
        const materialFilter = document.getElementById('material-filter').value;
        const colorFilter = document.getElementById('color-filter').value;
        const styleFilter = document.getElementById('style-filter').value;
    
        document.querySelectorAll('.texture-btn').forEach(btn => {
            const properties = btn.querySelectorAll('.texture-properties');
            const material = properties[0]?.textContent;
            const color = properties[1]?.textContent;
            const style = properties[2]?.textContent;
    
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
    generateTextureButtons() {
        const textureContent = document.getElementById('texture-content');
        if (!textureContent) return;

        // Clear existing buttons
        textureContent.innerHTML = '';

        // Use the fabricTextures array (populated from API)
        this.fabricTextures.forEach(fabric => {
            // Create button element
            const btn = document.createElement('div');
            btn.className = 'texture-btn';
            btn.dataset.texture = fabric.id;

            // Texture image
            const imgDiv = document.createElement('div');
            imgDiv.className = 'texture-image';
            imgDiv.style.backgroundImage = `url('${fabric.image_url || fabric.file}')`;
            btn.appendChild(imgDiv);

            // Info container
            const infoDiv = document.createElement('div');
            infoDiv.className = 'texture-info';

            // Name
            const nameDiv = document.createElement('div');
            nameDiv.className = 'texture-name';
            nameDiv.textContent = fabric.name || '';
            infoDiv.appendChild(nameDiv);

            // Material
            const materialDiv = document.createElement('div');
            materialDiv.className = 'texture-properties';
            materialDiv.textContent = fabric.properties?.material || fabric.material || '';
            infoDiv.appendChild(materialDiv);

            // Color
            const colorDiv = document.createElement('div');
            colorDiv.className = 'texture-properties';
            colorDiv.textContent = fabric.properties?.color || fabric.color || '';
            infoDiv.appendChild(colorDiv);

            // Style
            const styleDiv = document.createElement('div');
            styleDiv.className = 'texture-properties';
            styleDiv.textContent = fabric.properties?.style || fabric.style || '';
            infoDiv.appendChild(styleDiv);

            // Extra property (optional)
            if (fabric.properties?.extra || fabric.extra) {
                const extraDiv = document.createElement('div');
                extraDiv.className = 'texture-properties';
                extraDiv.textContent = fabric.properties?.extra || fabric.extra;
                infoDiv.appendChild(extraDiv);
            }

            // Price
            const priceDiv = document.createElement('div');
            priceDiv.className = 'texture-price';
            priceDiv.textContent = (fabric.price ? `${fabric.price.toFixed(2)} лв.` : ''); // or use price_adjustment if needed
            infoDiv.appendChild(priceDiv);

            btn.appendChild(infoDiv);

            // --- APPLY TEXTURE ON CLICK ---
            btn.addEventListener('click', () => {
                const apiTextureId = btn.dataset.texture;
                const textureName = this.mapTextureId(apiTextureId);
                
                // console.log("Texture button clicked:", apiTextureId, "mapped to:", textureName);
                // console.log("Texture exists in window.textures:", window.textures && !!window.textures[textureName]);
                // console.log("ShirtModel exists:", !!window.shirtModel);
                
                // Check if window.textures exists before accessing it
                if (!window.textures) {
                    console.error("window.textures is undefined!");
                    return;
                }
                
                // shirtModel and textures are defined in script.js, so access via window
                if (window.shirtModel && window.textures[textureName]) {
                    localStorage.setItem('selectedTexture', apiTextureId); // Store the API ID
                    // Remove active class from all buttons
                    document.querySelectorAll('.texture-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    btn.classList.add('active');
                    window.shirtModel.traverse((child) => {
                        if (child.isMesh) {
                            child.material.map = window.textures[textureName];
                            
                            // Better material properties for color accuracy
                            child.material.roughness = 0.8;
                            child.material.metalness = 0.0;
                            child.material.normalScale = new THREE.Vector2(1.0, 1.0);
                            child.material.envMapIntensity = 0.5;
                            
                            // Ensure proper color encoding
                            if (child.material.map) {
                                child.material.map.encoding = THREE.sRGBEncoding;
                                child.material.map.colorSpace = THREE.SRGBColorSpace;
                            }
                            
                            child.material.needsUpdate = true;
                        }
                    });
                } else {
                    console.error("Cannot apply texture:", textureName);
                    // console.log("Available textures:", Object.keys(window.textures || {}));
                }
                if (window.wooCommerceConnector && typeof window.wooCommerceConnector.updatePriceDisplay === 'function') {
                    window.wooCommerceConnector.updatePriceDisplay();
                }
                if (window.innerWidth <= 768) {
                    document.getElementById('texture-sidebar').classList.remove('active');
                }
            });

            textureContent.appendChild(btn);
        });
    }


    // Update size options for the current model
    updateSizeOptions(modelType) {
        if (!modelType) {
            const activeModelBtn = document.querySelector('.model-btn.active');
            modelType = activeModelBtn ? activeModelBtn.dataset.model : 'men1';
        }
        
        const sizeSelect = document.getElementById('shirt-size');
        if (!sizeSelect) return;
        
        // Clear existing options
        sizeSelect.innerHTML = '';
        
        // Get sizes for the selected model
        const sizes = this.modelSizes[modelType] || [];
        
        // If no sizes from WordPress, use default sizes
        if (!sizes || sizes.length === 0) {
            const defaultSizes = {
                'men1': [
                    { value: 'XS', label: 'XS' },
                    { value: 'S', label: 'S' },
                    { value: 'M', label: 'M' },
                    { value: 'L', label: 'L' },
                    { value: 'XL', label: 'XL' },
                    { value: 'XXL', label: 'XXL' }
                ],
                'men2': [
                    { value: '40', label: '40' },
                    { value: '42', label: '42' },
                    { value: '44', label: '44' },
                    { value: '46', label: '46' },
                    { value: '48', label: '48' }
                ],
                'men3': [
                    { value: 'XS', label: 'XS' },
                    { value: 'S', label: 'S' },
                    { value: 'M', label: 'M' },
                    { value: 'L', label: 'L' },
                    { value: 'XL', label: 'XL' }
                ],
                'women1': [
                    { value: '36', label: '36' },
                    { value: '38', label: '38' },
                    { value: '40', label: '40' },
                    { value: '42', label: '42' },
                    { value: '44', label: '44' }
                ],
                'women2': [
                    { value: 'XS', label: 'XS' },
                    { value: 'S', label: 'S' },
                    { value: 'M', label: 'M' },
                    { value: 'L', label: 'L' },
                    { value: 'XL', label: 'XL' }
                ]
            };
            
            const options = defaultSizes[modelType] || defaultSizes['men1'];
            
            // Add options to select
            options.forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option.value;
                optionElement.textContent = option.label;
                sizeSelect.appendChild(optionElement);
            });
        } else {
            // Add options from WordPress data
            sizes.forEach(size => {
                const optionElement = document.createElement('option');
                optionElement.value = size.value;
                optionElement.textContent = size.label;
                sizeSelect.appendChild(optionElement);
            });
        }
    }

    // Update the price display
    updatePriceDisplay() {
        const priceDisplay = document.getElementById('price-display');
        if (!priceDisplay) return;
        
        const activeModelBtn = document.querySelector('.model-btn.active');
        const activeTextureBtn = document.querySelector('.texture-btn.active');
        
        const modelType = activeModelBtn ? activeModelBtn.dataset.model : 'men1';
        const textureId = activeTextureBtn ? activeTextureBtn.dataset.texture : null;
        
        // Get base model price
        let totalPrice = this.modelPrices[modelType] || 119.00;
        
        // Add texture price adjustment if available
        if (textureId && this.fabricTextures) {
            const selectedTexture = this.fabricTextures.find(fabric => fabric.id === textureId);
            if (selectedTexture && selectedTexture.price_adjustment) {
                totalPrice += parseFloat(selectedTexture.price_adjustment);
            }
        }
        
        // Update the price display
        priceDisplay.textContent = totalPrice.toFixed(2) + ' лв.';
    }

    addToCart(textureId, modelType, size, buttonColor = 'Бели') {
    return new Promise((resolve, reject) => {
        // Get quantity from input
        const quantityInput = document.getElementById('shirt-quantity');
        const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
        
        // Prepare the data to send
        const formData = new FormData();
        formData.append('action', 'add_shirt_to_cart');
        formData.append('texture', textureId);
        formData.append('model', modelType);
        formData.append('size', size || 'M');
        formData.append('quantity', quantity); // Add quantity parameter
        formData.append('button_color', buttonColor);
            
        // Use WordPress admin-ajax.php endpoint which is more reliable
        fetch(`${this.siteUrl}/wp-admin/admin-ajax.php`, {
            method: 'POST',
            credentials: 'include',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // console.log('Product added to cart successfully');
                resolve(data);
            } else {
                console.error('Error adding to cart:', data.message);
                reject(new Error(data.message || 'Error adding to cart'));
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            reject(error);
        });
    });
}

    
    // Save filter values
    saveFilters() {
        const filters = {
            material: document.getElementById('material-filter').value,
            color: document.getElementById('color-filter').value,
            style: document.getElementById('style-filter').value
        };
        localStorage.setItem('textureFilters', JSON.stringify(filters));
    }

    // Add this method to map API texture IDs to the actual texture keys
    mapTextureId(apiTextureId) {
        // If the API returns numeric IDs like "1", "2", etc., convert to "fabric1", "fabric2"
        if (/^\d+$/.test(apiTextureId)) {
            return `fabric${apiTextureId}`;
        }
        // If the API already returns "fabric1", "fabric2", etc., return as is
        return apiTextureId;
    }
}

// Create and export a single instance
const wooCommerceConnector = new WooCommerceConnector();

// Make it available globally
window.wooCommerceConnector = wooCommerceConnector;

export default wooCommerceConnector;

// Add event listeners for filters
document.addEventListener('DOMContentLoaded', () => {
    const materialFilter = document.getElementById('material-filter');
    const colorFilter = document.getElementById('color-filter');
    const styleFilter = document.getElementById('style-filter');
    
    if (materialFilter) {
        materialFilter.addEventListener('change', () => {
            wooCommerceConnector.filterTextures();
            wooCommerceConnector.saveFilters();
        });
    }
    
    if (colorFilter) {
        colorFilter.addEventListener('change', () => {
            wooCommerceConnector.filterTextures();
            wooCommerceConnector.saveFilters();
        });
    }
    
    if (styleFilter) {
        styleFilter.addEventListener('change', () => {
            wooCommerceConnector.filterTextures();
            wooCommerceConnector.saveFilters();
        });
    }
});

