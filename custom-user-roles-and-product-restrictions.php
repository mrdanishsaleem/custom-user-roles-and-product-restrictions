<?php
/*
Plugin Name: Custom User Roles and Product Restrictions
Description: Allow administrators to create, read, update, and delete custom user roles. Show products based on user role. Add a "Private Product" checkbox to products.
Version: 1.0
Author: Ammara Tahir
*/

function custom_roles_settings_page()
{
    add_menu_page(
        'Custom User Roles',
        'Custom User Roles',
        'manage_options',
        'custom-user-roles',
        'render_custom_roles_settings_page'
    );
}
add_action('admin_menu', 'custom_roles_settings_page');

function render_custom_roles_settings_page()
{
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['role'])) {
        $role = sanitize_text_field($_GET['role']);
        remove_role($role);
        add_settings_error(
            'custom-user-roles',
            'role-deleted',
            'Role deleted successfully.',
            'success'
        );
    } elseif (isset($_POST['create_role'])) {
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
    } elseif (isset($_POST['edit_role_submit'])) {
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

    settings_errors('custom-user-roles');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2>Create New Role</h2>
        <form method="post" action="">
            <input type="text" name="role_name" placeholder="Enter Role Name">
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
                <?php
                $wp_roles = wp_roles();
                foreach ($wp_roles->roles as $role => $details) {
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
                ?>
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

add_action("woocommerce_product_options_general_product_data", function () {
    global $post;
    $existing_private_product = get_post_meta($post->ID, '_private_product', true);
    $checked = $existing_private_product === 'yes' ? 'yes' : 'no';
?>
    <div class="options_group">
        <?php
        woocommerce_wp_checkbox(
            array(
                'id'            => '_private_product',
                'label'         => __('Private Product', 'custom-user-roles'),
                'description'   => __('Check this box if the product is private.', 'custom-user-roles'),
                'value'         => $checked // Set the value based on whether it's already selected or not
            )
        );

        if ($existing_private_product === 'yes') {
            $wp_roles = wp_roles();
            if (!empty($wp_roles->roles)) {
                echo '<div id="private_product_roles">';
                echo '<p class="form-field">';
                echo '<label for="private_product_roles">' . __('Select User Roles', 'custom-user-roles') . '</label>';
                foreach ($wp_roles->roles as $role => $details) {
                    echo '<input type="checkbox" class="checkbox" name="private_product_roles[]" value="' . esc_attr($role) . '" />' . esc_html($details['name']) . '<br>';
                }
                echo '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#_private_product').change(function() {
                if ($(this).is(':checked')) {
                    $('#private_product_roles').show();
                } else {
                    $('#private_product_roles').hide();
                }
            });
        });
    </script>
<?php
});

add_action("save_post_product", function ($post_ID, $product, $update) {
    $private_product = isset($_POST["_private_product"]) ? "yes" : "no";
    update_post_meta($post_ID, "_private_product", $private_product);

    if ($private_product === "yes" && isset($_POST['private_product_roles'])) {
        $selected_roles = array_map('sanitize_text_field', $_POST['private_product_roles']);
        update_post_meta($post_ID, "_private_product_roles", $selected_roles);
    } else {
        delete_post_meta($post_ID, "_private_product_roles");
    }
}, 10, 3);
