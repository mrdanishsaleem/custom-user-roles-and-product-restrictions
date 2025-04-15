<?php
/*
Plugin Name: Custom User Roles and Product Restrictions
Description: Allow administrators to create, read, update, and delete custom user roles. Show products based on user role. Add a "Private Product" checkbox to products.
Version: 1.0
Author: Ammara Tahir
*/

// Add menu page for custom user roles
function custom_roles_settings_page()
{
    add_menu_page(
        'Custom User Roles',
        'Custom User Roles',
        'manage_options',
        'custom-user-roles',
        'render_custom_roles_settings_page',
        'dashicons-groups'
    );
}
add_action('admin_menu', 'custom_roles_settings_page');

// Render custom user roles settings page
function render_custom_roles_settings_page()
{
    // Handle role actions
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['role'])) {
        delete_custom_role();
    } elseif (isset($_POST['create_role'])) {
        create_custom_role();
    } elseif (isset($_POST['edit_role_submit'])) {
        update_custom_role();
    }

    // Render the settings page content
    render_custom_roles_page_content();
}

// Create a custom user role
function create_custom_role()
{
    $role_name = sanitize_text_field($_POST['role_name']);
    $capabilities = array(
        'read' => true,
    );
    add_role($role_name, $role_name, $capabilities);
    add_settings_error(
        'custom-user-roles',
        'role-added',
        'Role added successfully.',
        'success'
    );
}

// Update a custom user role
function update_custom_role()
{
    if (!isset($_POST['edit_role']) || !isset($_POST['edited_role_name'])) return;

    $edited_role = sanitize_text_field($_POST['edit_role']);
    $edited_role_name = sanitize_text_field($_POST['edited_role_name']);
    $wp_roles = wp_roles();
    $wp_roles->roles[$edited_role]['name'] = $edited_role_name;
    $wp_roles->role_names[$edited_role] = $edited_role_name;
    update_option('wp_user_roles', $wp_roles->roles);
    add_settings_error(
        'custom-user-roles',
        'role-updated',
        'Role updated successfully.',
        'success'
    );
}

// Delete a custom user role
function delete_custom_role()
{
    $role = sanitize_text_field($_GET['role']);
    remove_role($role);
    add_settings_error(
        'custom-user-roles',
        'role-deleted',
        'Role deleted successfully.',
        'success'
    );

    // Redirect to the custom user roles page
    wp_redirect(admin_url('admin.php?page=custom-user-roles'));
    exit;
}

// Render the content of custom user roles settings page
function render_custom_roles_page_content()
{
    // Display any settings errors
    settings_errors('custom-user-roles');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2>Create New Role</h2>
        <form method="post" action="">
            <input type="text" name="role_name" placeholder="e.g: new_custom_role">
            <button type="submit" name="create_role">Create Role</button>
            <input type="hidden" name="page" value="custom-user-roles">
        </form>

        <h2>Existing Roles</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Role Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php render_existing_roles(); ?>
            </tbody>
        </table>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-button');
            editButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const roleId = this.getAttribute('data-role');
                    const editRow = document.getElementById('edit-row-' + roleId);
                    if (editRow.style.display === 'none') {
                        editRow.style.display = 'table-row';
                    } else {
                        editRow.style.display = 'none';
                    }
                });
            });
        });
    </script>
<?php
}

// Render existing custom roles
function render_existing_roles()
{
    $wp_roles = wp_roles();
    $excluded_roles = array(
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
        'customer',
        'shop_manager'
    );

    foreach ($wp_roles->roles as $role => $details) {
        if (!in_array($role, $excluded_roles)) {
            echo '<tr>';
            echo '<td>' . esc_html($details['name']) . '</td>';
            echo '<td class="button-group">';
            echo '<button class="button button-primary edit-button" data-role="' . esc_attr($role) . '">Edit</button>';
            echo '&nbsp;';
            echo '<a href="?page=custom-user-roles&action=delete&role=' . esc_attr($role) . '" class="button button-secondary delete-button">Delete</a>';
            echo '</td>';
            echo '</tr>';
            echo '<tr class="edit-row" id="edit-row-' . esc_attr($role) . '" style="display:none;">';
            echo '<td colspan="2">';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="edit_role" value="' . esc_attr($role) . '">';
            echo '<input type="text" name="edited_role_name" value="' . esc_attr($details['name']) . '" placeholder="Enter New Role Name">';
            echo '<button type="submit" name="edit_role_submit" class="button button-primary">Update Role</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
    }
}

// Add custom field for "Private Product" checkbox in product options
add_action("woocommerce_product_options_general_product_data", function () {
    global $post;
    $existing_private_product = get_post_meta($post->ID, '_private_product', true);
    $checked = $existing_private_product === 'yes' ? 'yes' : 'no';
?>
    <div class="options_group">
        <?php render_private_product_checkbox($existing_private_product, $checked); ?>
    </div>
    <div id="private_product_roles_container" style="display: <?php echo $checked === 'yes' ? 'block' : 'none'; ?>">
        <?php render_private_product_roles(); ?>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#_private_product').change(function() {
                if ($(this).is(':checked')) {
                    $('#private_product_roles_container').show();
                } else {
                    $('#private_product_roles_container').hide();
                }
            });

            $('#_private_product').trigger('change');
        });
    </script>
<?php
});

// Render private product checkbox
function render_private_product_checkbox($existing_private_product, $checked)
{
    woocommerce_wp_checkbox(
        array(
            'id'            => '_private_product',
            'label'         => __('Private Product', 'custom-user-roles'),
            'description'   => __('Check this box if the product is private.', 'custom-user-roles'),
            'value'         => $checked
        )
    );
}

// Render private product roles checkbox
function render_private_product_roles()
{
    global $post;

    $existing_private_product_roles = get_post_meta($post->ID, '_private_product_roles', true);

    $wp_roles = wp_roles();
    $default_roles = array(
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
        'customer',
        'shop_manager'
    );

    if (!empty($wp_roles->roles)) {
        echo '<div id="private_product_roles">';
        echo '<p class="form-field">';
        echo '<label for="private_product_roles">' . __('Select User Roles', 'custom-user-roles') . '</label>';

        foreach ($wp_roles->roles as $role => $details) {
            // Check if the role is not in the default roles list
            if (!in_array($role, $default_roles)) {
                $checked = in_array($role, (array) $existing_private_product_roles) ? 'checked' : '';
                echo '<input type="checkbox" class="checkbox" name="private_product_roles[]" value="' . esc_attr($role) . '" ' . $checked . ' />' . esc_html($details['name']) . '<br>';
            }
        }
        echo '</p>';
        echo '</div>';
    }
}

// Save private product settings
add_action("save_post_product", function ($post_ID) {
    $private_product = isset($_POST["_private_product"]) ? "yes" : "no";
    update_post_meta($post_ID, "_private_product", $private_product);

    if ($private_product === "yes" && isset($_POST['private_product_roles'])) {
        $selected_roles = array_map('sanitize_text_field', $_POST['private_product_roles']);
        update_post_meta($post_ID, "_private_product_roles", $selected_roles);
    } else {
        delete_post_meta($post_ID, "_private_product_roles");
    }
    if ($private_product === "yes") {
        update_post_meta($post_ID, "_private_product", "yes");
    }
});

// Hide products with private meta key
// add_filter('woocommerce_product_query_meta_query', 'hide_products_with_private_meta_key', 10, 2);

function hide_products_with_private_meta_key($meta_query, $query)
{
    if (!is_shop()) return $meta_query;

    $meta_query[] = array(
        'key'     => '_private_product',
        'value'   => 'yes',
        'compare' => '!='
    );

    return $meta_query;
}

// Filter products by user roles
add_action('woocommerce_product_query', 'filter_products_by_user_roles');

function filter_products_by_user_roles($query)
{


    if (!is_user_logged_in()) {
        // Visitors without login should see all public products
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key'     => '_private_product',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_private_product',
                'value'   => 'no',
                'compare' => '='
            )
        );

        $query->set('meta_query', $meta_query);
        return;
    } else {
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $user = get_userdata($current_user_id);
            if ($user) {
                $user_roles = $user->roles; // Array of user roles

                // Check if the user has a specific role
                if (in_array('administrator', $user_roles)) {
                    return;
                }
            }
        }
    }

    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;

    if (!empty($user_roles)) {
        $meta_query = array('relation' => 'OR');

        // Add condition for products with no custom user roles assigned
        $meta_query[] = array(
            'key' => '_private_product_roles',
            'compare' => 'NOT EXISTS',
        );

        // Add condition for products with roles matching the user's roles
        foreach ($user_roles as $role) {
            $meta_query[] = array(
                'key' => '_private_product_roles',
                'value' => $role,
                'compare' => 'LIKE',
            );
        }

        $query->set('meta_query', $meta_query);
    }
}


// Validate add to cart by role
add_filter('woocommerce_add_to_cart_validation', 'restrict_add_to_cart_by_role', 10, 3);

function restrict_add_to_cart_by_role($passed, $product_id, $quantity)
{
    $product = wc_get_product($product_id);
    $selected_roles = get_post_meta($product_id, '_private_product_roles', true);

    if ($product && is_array($selected_roles) && !empty($selected_roles)) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_roles = $user->roles;

            $allowed = false;
            foreach ($selected_roles as $role) {
                if (in_array($role, $user_roles)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                wc_add_notice(__('You do not have permission to add this product to the cart.', 'custom-user-roles'), 'error');
                return false;
            }
        } else {
            wc_add_notice(sprintf(
                __('Please <a href="%s">log in</a> or <a href="%s">register</a> to add this product to the cart.', 'custom-user-roles'),
                wp_login_url(get_permalink()),
                wp_registration_url()
            ), 'error');
            return false;
        }
    }

    return $passed;
}

