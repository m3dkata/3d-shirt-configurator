/**
 * Admin JavaScript for Shirt Configurator
 */
(function($) {
    'use strict';
    
    // Initialize color pickers
    $('.color-picker').wpColorPicker();
    
    // Make tables sortable
    if ($.fn.sortable) {
        $('.sortable-table tbody').sortable({
            handle: '.sort-handle',
            update: function(event, ui) {
                // Update order after sorting
                let items = [];
                $(this).find('tr').each(function(index) {
                    items.push({
                        id: $(this).data('id'),
                        order: index
                    });
                });
                
                // Save new order via AJAX
                $.ajax({
                    url: shirt_config.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'update_item_order',
                        items: items,
                        nonce: shirt_config.nonce
                    },
                    success: function(response) {
                        if (!response.success) {
                            alert(response.data);
                        }
                    }
                });
            }
        });
    }
    
    // Preview image on file selection
    $('.texture-file-input').on('change', function() {
        const file = this.files[0];
        const preview = $(this).closest('.form-field').find('.texture-preview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.attr('src', e.target.result);
                preview.show();
            };
            reader.readAsDataURL(file);
        } else {
            preview.hide();
        }
    });
    
    // Confirm deletion
    $('.delete-button').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Dynamic form fields for size options
    let sizeFieldIndex = $('.size-option').length;
    
    $('#add-size-option').on('click', function() {
        const template = $('#size-option-template').html();
        const newField = template.replace(/\{index\}/g, sizeFieldIndex++);
        $('#size-options-container').append(newField);
    });
    
    $(document).on('click', '.remove-size-option', function() {
        $(this).closest('.size-option').remove();
    });
    
    // Toggle advanced settings
    $('.toggle-advanced').on('click', function(e) {
        e.preventDefault();
        const target = $(this).data('target');
        $('#' + target).slideToggle();
        $(this).find('span').toggleClass('dashicons-arrow-down dashicons-arrow-up');
    });
    
    // Initialize tooltips
    $('.tooltip-icon').on('mouseenter', function() {
        $(this).next('.tooltip-content').show();
    }).on('mouseleave', function() {
        $(this).next('.tooltip-content').hide();
    });
    
    // Bulk actions
    $('#bulk-action-button').on('click', function() {
        const action = $('#bulk-action-selector').val();
        if (!action) return;
        
        const selectedItems = $('.bulk-select:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedItems.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        
        if (action === 'delete' && !confirm('Are you sure you want to delete the selected items?')) {
            return;
        }
        
        // Process bulk action via AJAX
        $.ajax({
            url: shirt_config.ajax_url,
            type: 'POST',
            data: {
                action: 'bulk_' + action + '_items',
                items: selectedItems,
                item_type: $('#bulk-item-type').val(),
                nonce: shirt_config.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });
    
        // Toggle all checkboxes
        $('#select-all').on('change', function() {
            $('.bulk-select').prop('checked', $(this).prop('checked'));
        });
        
        // Update model preview when file is selected
        $('#model-file').on('change', function() {
            const modelFile = $(this).val();
            if (modelFile && modelFile.endsWith('.glb')) {
                // Show model preview if we have a 3D viewer
                if (typeof ModelViewer !== 'undefined') {
                    const previewContainer = $('#model-preview-container');
                    previewContainer.html('<model-viewer src="' + modelFile + '" auto-rotate camera-controls></model-viewer>');
                    previewContainer.show();
                }
            }
        });
        
        // Filter textures by properties
        $('#texture-filter-form select').on('change', function() {
            filterTextures();
        });
        
        function filterTextures() {
            const material = $('#filter-material').val();
            const color = $('#filter-color').val();
            const style = $('#filter-style').val();
            
            $('.texture-item').each(function() {
                const $item = $(this);
                const itemMaterial = $item.data('material');
                const itemColor = $item.data('color');
                const itemStyle = $item.data('style');
                
                const materialMatch = !material || itemMaterial === material;
                const colorMatch = !color || itemColor === color;
                const styleMatch = !style || itemStyle === style;
                
                if (materialMatch && colorMatch && styleMatch) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        }
        
        // Reset filters
        $('#reset-filters').on('click', function() {
            $('#texture-filter-form select').val('');
            filterTextures();
        });
        
        // Price calculator
        function updateTotalPrice() {
            const modelPrice = parseFloat($('#model-base-price').val()) || 0;
            const textureAdjustment = parseFloat($('#texture-price-adjustment').val()) || 0;
            const customAdjustment = parseFloat($('#custom-price-adjustment').val()) || 0;
            
            const total = modelPrice + textureAdjustment + customAdjustment;
            $('#total-price-display').text(total.toFixed(2));
        }
        
        $('#model-base-price, #texture-price-adjustment, #custom-price-adjustment').on('input', updateTotalPrice);
        
        // Initialize on page load
        updateTotalPrice();
        
        // Import/Export functionality
        $('#export-data').on('click', function() {
            $.ajax({
                url: shirt_config.ajax_url,
                type: 'POST',
                data: {
                    action: 'export_configurator_data',
                    nonce: shirt_config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link for JSON file
                        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data));
                        const downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", "shirt_configurator_export.json");
                        document.body.appendChild(downloadAnchorNode);
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                    } else {
                        alert(response.data);
                    }
                }
            });
        });
        
        // Import data
        $('#import-form').on('submit', function(e) {
            e.preventDefault();
            
            const fileInput = $('#import-file')[0];
            if (fileInput.files.length === 0) {
                alert('Please select a file to import.');
                return;
            }
            
            const file = fileInput.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    
                    // Confirm import
                    if (confirm('Are you sure you want to import this data? This may overwrite existing configurations.')) {
                        // Send data to server
                        $.ajax({
                            url: shirt_config.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'import_configurator_data',
                                data: data,
                                nonce: shirt_config.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Data imported successfully!');
                                    location.reload();
                                } else {
                                    alert('Error: ' + response.data);
                                }
                            }
                        });
                    }
                } catch (error) {
                    alert('Invalid JSON file. Please upload a valid export file.');
                }
            };
            
            reader.readAsText(file);
        });
        
        // Analytics tab functionality
        if ($('#sales-chart').length) {
            $.ajax({
                url: shirt_config.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_configurator_analytics',
                    period: 'month',
                    nonce: shirt_config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        renderSalesChart(response.data);
                    }
                }
            });
        }
        
        function renderSalesChart(data) {
            const ctx = document.getElementById('sales-chart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Sales',
                        data: data.values,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Period selector for analytics
        $('#analytics-period').on('change', function() {
            const period = $(this).val();
            
            $.ajax({
                url: shirt_config.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_configurator_analytics',
                    period: period,
                    nonce: shirt_config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Destroy existing chart and create new one
                        if (window.salesChart) {
                            window.salesChart.destroy();
                        }
                        renderSalesChart(response.data);
                    }
                }
            });
        });
        
        // Popular combinations table
        $('#popular-period').on('change', function() {
            const period = $(this).val();
            
            $.ajax({
                url: shirt_config.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_popular_combinations',
                    period: period,
                    nonce: shirt_config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update table content
                        let html = '';
                        if (response.data.length === 0) {
                            html = '<tr><td colspan="4">No data available</td></tr>';
                        } else {
                            response.data.forEach(function(item, index) {
                                html += `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${item.model_name}</td>
                                        <td>${item.texture_name}</td>
                                        <td>${item.count}</td>
                                    </tr>
                                `;
                            });
                        }
                        $('#popular-combinations-table tbody').html(html);
                    }
                }
            });
        });
        
        // Initialize tooltips
        if (typeof tippy !== 'undefined') {
            tippy('[data-tippy-content]');
        }
        
    })(jQuery);
    
