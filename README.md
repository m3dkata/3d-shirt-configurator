# 3D Shirt Configurator

A modern, interactive 3D shirt configurator built with Three.js and integrated with WooCommerce. This application allows customers to customize shirts with different models, textures, sizes, and button colors, then add them to their cart with real-time pricing.

![3D Shirt Configurator](assets/3d-shirt-configurator.jpg)

## Features

### 🎨 3D Visualization
- **Interactive 3D Models**: Multiple shirt models (men's and women's) with realistic rendering
- **Real-time Texture Mapping**: Apply different fabric textures to 3D models
- **Advanced Lighting**: Professional lighting setup with shadows and reflections
- **Smooth Animations**: Fluid model rotations, zoom, and positioning controls

### 🛍️ Customization Options
- **Multiple Shirt Models**: 
  - Men's Classic Shirt (Model 6)
  - Men's Slim Fit Shirt (Model 4) 
  - Men's Casual Shirt (Model 6-1)
  - Women's Classic Shirt (Model 5)
  - Women's Fitted Shirt (Model 7)
- **Fabric Selection**: 75+ different fabric textures with material properties
- **Size Options**: Model-specific sizing (XS-5XL for most models)
- **Button Colors**: 7 different button color options
- **Advanced Try-On**: AI-powered virtual try-on using BodyPix and pose detection

### 🛒 E-commerce Integration
- **WooCommerce Integration**: Seamless integration with WooCommerce shopping cart
- **Dynamic Pricing**: Real-time price calculation based on model and fabric selection
- **Custom Product Data**: Store all customization details in cart and orders
- **Admin Management**: Comprehensive admin interface for managing models, textures, and pricing

### 📱 User Experience
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Progressive Web App**: PWA capabilities for app-like experience
- **Touch Controls**: Intuitive touch controls for mobile users
- **Loading Indicators**: Visual feedback during model and texture loading

## Technology Stack

### Frontend
- **Three.js**: 3D rendering and WebGL
- **TensorFlow.js**: AI-powered pose detection and body segmentation
- **MediaPipe**: Advanced pose estimation
- **Font Awesome**: Icon library
- **Vanilla JavaScript**: No heavy frameworks, optimized performance

### Backend
- **WordPress**: CMS and content management
- **WooCommerce**: E-commerce platform
- **REST API**: Custom endpoints for configurator data
- **MySQL**: Database for models, textures, and pricing

### 3D Assets
- **GLTF/GLB**: 3D model format
- **DRACO Compression**: Optimized 3D model loading
- **Texture Mapping**: High-quality fabric textures

## Installation

### Prerequisites
- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Steps

1. **Upload Files**
   - Upload all files to your WordPress installation directory
   - Ensure proper file permissions (755 for directories, 644 for files)

2. **Install Plugin**
   - The `shirt-configurator-woocommerce.php` file will be automatically recognized as a WordPress plugin
   - Navigate to WordPress Admin → Plugins and activate "3D Shirt Configurator Integration"

3. **Configure Settings**
   - Go to WooCommerce → Configuration → Shirt Configurator
   - Set up your models, textures, and pricing options
   - Configure the WooCommerce product integration

4. **Create Configurator Page**
   - Create a new WordPress page with the template "Shirt Configurator"
   - The page will automatically display the 3D configurator

5. **Configure Domain**
   - Update all references from `https://shfashion.bg` to `https://(server-domain)` in:
     - `js/script.js`
     - `index.html`
     - `shirt-configurator-woocommerce.php`

## Usage

### For Customers
1. **Select Model**: Choose from available shirt models
2. **Choose Fabric**: Browse and select from fabric textures
3. **Pick Size**: Select the appropriate size for your model
4. **Customize Buttons**: Choose button color from available options
5. **Try On**: Use the virtual try-on feature to see how the shirt looks
6. **Add to Cart**: Configure and add your custom shirt to cart

### For Administrators
1. **Manage Models**: Add, edit, or remove 3D shirt models
2. **Organize Textures**: Upload and categorize fabric textures
3. **Set Pricing**: Configure pricing for model-fabric combinations
4. **Customize Sizes**: Set size options for each model
5. **Monitor Orders**: View detailed customization data in WooCommerce orders

## API Endpoints

The plugin provides several REST API endpoints:

- `GET /wp-json/shirt-configurator/v1/get-config` - Get configuration data
- `GET /wp-json/shirt-configurator/v1/init` - Initialize configurator
- `POST /wp-json/shirt-configurator/v1/add-to-cart` - Add configured shirt to cart
- `GET /wp-json/shirt-configurator/v1/models` - Get all models
- `GET /wp-json/shirt-configurator/v1/textures` - Get all textures

## Database Structure

The plugin creates several database tables:

- `wp_shirt_models` - 3D model information
- `wp_shirt_textures` - Fabric texture data
- `wp_shirt_pricing` - Custom pricing combinations
- `wp_shirt_model_sizes` - Size options per model

## Customization

### Adding New Models
1. Prepare your GLB file with proper UV mapping
2. Add model entry in the WordPress admin
3. Configure pricing and sizes
4. Update the frontend model buttons

### Adding New Textures
1. Prepare fabric images (recommended: 1024x1024)
2. Add texture details in the WordPress admin
3. Categorize by material, color, and style
4. Set price adjustments if needed

### Styling
- Edit `css/styles.css` for visual customization
- Modify `js/script.js` for behavior changes
- Update `index.html` for layout adjustments

## Performance Optimization

### 3D Performance
- **DRACO Compression**: Models are compressed for faster loading
- **Texture Atlases**: Multiple textures combined for efficiency
- **Level of Detail**: Automatic quality adjustment based on device
- **Lazy Loading**: Resources load on demand

### Web Performance
- **Service Worker**: Offline functionality with `sw.js`
- **Caching**: Proper caching headers for static assets
- **CDN Ready**: Optimized for CDN delivery
- **Image Optimization**: WebP format support with fallbacks

## Browser Support

- **Chrome**: Full support
- **Firefox**: Full support
- **Safari**: Full support
- **Edge**: Full support
- **Mobile**: iOS Safari and Chrome for Android

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the GPL-3.0 License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue in the GitHub repository
- Check the documentation in the admin panel
- Review the API endpoints for integration

## Changelog

### Version 1.1
- Added virtual try-on feature with AI pose detection
- Improved mobile responsiveness
- Enhanced texture loading performance
- Added custom pricing management
- WooCommerce integration improvements

### Version 1.0
- Initial release with basic 3D configurator
- WooCommerce integration
- Multiple shirt models
- Fabric texture selection
- Size and color options

## Acknowledgments

- **Three.js** for 3D rendering capabilities
- **TensorFlow.js** for AI-powered features
- **MediaPipe** for pose detection
- **Font Awesome** for icon library
- **WooCommerce** for e-commerce platform

---

Built with ❤️ for the future of online fashion retail