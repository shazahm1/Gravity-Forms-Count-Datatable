Display the number of entries of a Gravity Form or display datatable of entries sorted alphabetically by WP User with their submission count using shortcodes.

# Shortcodes

- `[gf_count_entries]` :: Display the entry count by form ID.
- `[gf_count_datatable]` :: Display the entry count by WP User by form ID.
 
# Shortcode Parameters

The following parameters are valid for both shortcodes.

- `form_id` *int*  **required** :: The Gravity Form ID to display the entry count. Multiple form ID's can be supplied.
- `status` *string* :: The form status to count. Valid `all`|`active`|`inactive`|`trash` Default `active`
- `is_read` *string* :: Filter entry count by whether or not if the entry has been read. Valid `yes`|`no` Default both.
- `is_starred` *string* :: Filter entry count by whether or not if the entry has been starred. Valid `yes`|`no` Default both.
- `created_by` *int* :: The WP User ID of Gravity Form entry.
- `filter_field` *int* :: The field ID of the form.
- `filter_operator` *string* :: The filter operator. Default `=`
- `filter_value` *string* :: The field value.
- `filter_mode` *string* :: The filter mode. Valid `all`|`any` Default `all`
- `start_date` *string* :: The start date to start counting form entries.
- `end_date` *string* :: The end date to end counting form entries.
- `date_format` *string* :: The date format supplied for both `start_date` and `end_date`. Default `m/d/Y`
- `page_size` *int* :: The maximum number of entries counted. Default: `10000`
- `addend` *float* :: Value to add to the count.
- `factor` *float* :: value to multiply the count by. NOTE: if both `addend` and `factor` parameters are supplied. Order of operations are followed. Count will be first multiplied by `factor` and then the `addend` added to the count.
- `sum` *string* :: Add the entry count of multiple Gravity Form entry counts. Valid parameters are all valid shortcode parameters.
- `format` *bool* :: Whether or not to format the displayed entry count. Default: `false`
- `decimals` *int* :: The number of decimal points. Default: `2`
- `dec_point` *string* :: The separator for the decimal point. Default: `.`
- `thousands_sep` *string* :: The thousands separator. Default: `,`

The following parameters are valid for the `[gf_count_datatable]` shortcode.

- `search` *bool* :: Whether or not to enable search, by name and/or date range, for the datatable.
- `thead` *string* :: The table column headers. Table column header values are delimited by a `|` (pipe). Default: `Name|Count`
- `tfoot` *string* :: The table column footers. Table column footer values are delimited by a `|` (pipe). Default: `|%sum%`
   - The `tfoot` parameter support two token. They are `%average%` and `%sum%`. The `%average%` token is replaced by the average of the total count. The `%sum%` token is replace by the total count.

**NOTES:**

- The `seach` parameter can only be enabled for a single instance of the `[gf_count_datatable]` per page.
- To filter entry count by the current logged in user set `created_by` to `current`.
- Multiple field filters can be supplied in a shortcode by appending `_{+d}` to the `filter_field`, `filter_operator`, `filter_value` shortcode parameters. Example: `form_id='2' filter_field_1='3' filter_value_1='Developer' filter_field_2='5' filter_value_2='Undecided'`.
   - You can add as many filters as you wish, simply increment the trailing digit.

# Examples

## Display entry count of a Gravity Form by ID.

`[gf_count_datatable form_id='84']`

## Display the entry count of a Gravity Form filtered by WP User ID.

`[gf_count_datatable form_id='84' create_by='2']`

## Display the entry count of a Gravity Form filtered by the currently logged in WP User.

`[gf_count_datatable form_id='84' create_by='current']`

## Display total count of multiple Gravity Forms by ID.

`[gf_count_datatable form_id='84,85']`

## Display entry count filtered form form field value.

`[gf_count_datatable form_id='85' filter_field=9 filter_value=213]`

## Display the entry sum of multiple forms entry filtered by form field value.

`[gf_count_entries sum='{form_id=84 filter_field=9 filter_value=213}|{form_id=133 filter_field=9 filter_value=214}']`

## Filter entries based on absolute dates.

- Week of 2/16-20/2020
   - `[gf_count_datatable form_id='85' start_date='02/16/2020' end_date='02/20/2020']`

- Week of 2/23-27/2020
   - `[gf_count_datatable form_id='85' start_date='02/23/2020' end_date='02/27/2020']`

## Filter entries based on relative dates.

- Today
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='today']`

- Since Yesterday
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='yesterday']`

- Only Yesterday
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='yesterday' end_date='yesterday']`

- Last Week
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='-2 week sunday' end_date='-1 week saturday']`

- This Week
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='-1 week sunday']`
   - The relative date of `-1 week sunday` translates to 2020-03-22 00:00:00 (assuming a date of 3/24/2020). This would be the start of the Sunday day which would be 12:00am Sunday morning on the 12hr clock.

- Last Month
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='first day of last month' end_date='last day of last month']`

- This Month
   - `[gf_count_datatable form_id=84 date_format='relative' start_date='first day of this month']`

