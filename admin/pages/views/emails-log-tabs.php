<h2 class="nav-tab-wrapper">
	<?php foreach ( $this->tabs as $key => $name ): ?>
		<a href="?page=<?php echo $this->get_menu_slug(); ?>&tab=<?php echo $key; ?>" class="nav-tab <?php echo $current_tab == $key ? 'nav-tab-active' : ''; ?>"><?php echo $name; ?></a>
	<?php endforeach; ?>
</h2>