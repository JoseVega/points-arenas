# Points and Arenas Class for WordPress Users
You can use this class to give points to users, remove points, move to different arenas or levels, and provide points for completing different milestones (courses, right answers, etc.)

This includes a options page that appears in "wp-admin > options > points and arenas". The options page requires the "ultimate fields" plugin.

You can use the object through the function VG_Points_Obj() , you need to call the create_table method once per site, for example, if you include this class in your plugins or themes, you can call it on plugin/theme activation. If you want to support multisite installs you can call this method when a new site is created.

It uses the singleton pattern.

# Internal settings

The settings are simple, you can change the properties in the object:
    var $table_name = 'points'; // Change the table and global fields name
		var $enable_arenas = true; // Enable the arenas logic
		var $enable_rankings = true; // Enable the rankings logic
		var $enable_limits = true; // Enable yearly limits
		var $starting_points = 1; // Points given to users on registration
    
You shouldn't modify the code in the class, instead you should overwrite those properties on runtime. Example:
VG_Points_Obj()->table_name = 'user_credit';

# Usage

This class is very easy to use. You just need to call the methods when the right action occurs. For example:
VG_Points_Obj()->add_victory($user_id);
VG_Points_Obj()->add_loss($user_id);

If you want to give points regardless of the event
VG_Points_Obj()->add_points_to_user($user_id, $points = 1, $type = 'in', $source = 'general');

If you want to remove points regardless of the event
VG_Points_Obj()->add_points_to_user($user_id, $points = 1, $type = 'out', $source = 'loss');

The user will automatically move to the next level/arena if he reaches the current arena limits set in the settings page in wp-admin.
