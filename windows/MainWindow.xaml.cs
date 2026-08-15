using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;

namespace SchoolSaaS.Desktop;

public partial class MainWindow : Window
{
    private readonly HttpClient _http;
    private readonly SyncQueue _queue;
    private readonly SyncService _sync;
    private string? _token;
    private string? _role;
    private string _selectedModule = "students";
    private readonly Dictionary<string, Control> _fields = new();

    private sealed record FieldSpec(string Key, string Label, string Type = "text", bool Required = false, string[]? Options = null, double? Min = null);

    private static readonly HashSet<string> ModuleEndpoints = [
        "students", "teachers", "employees", "classes", "subjects", "attendance", "timetable",
        "fees", "fee-structures", "payments", "expenses", "exams", "exam-subjects", "marks", "results", "payroll-runs", "payroll-items", "library-books", "library-loans", "transport-routes", "transport-assignments", "notices", "reports"
    ];

    private static readonly Dictionary<string, FieldSpec[]> ModuleFields = new()
    {
        ["students"] = [new("admission_no", "Admission number", Required: true), new("first_name", "First name", Required: true), new("last_name", "Last name"), new("gender", "Gender", Options: ["male", "female", "other"]), new("date_of_birth", "Date of birth", "date"), new("phone", "Phone"), new("address", "Address")],
        ["teachers"] = [new("employee_id", "Employee ID", Required: true), new("first_name", "First name", Required: true), new("last_name", "Last name"), new("phone", "Phone"), new("status", "Status", Options: ["active", "inactive"])],
        ["employees"] = [new("employee_code", "Employee code", Required: true), new("first_name", "First name", Required: true), new("last_name", "Last name"), new("role_title", "Role title", Required: true), new("email", "Email"), new("phone", "Phone"), new("salary", "Salary", "number", Min: 0)],
        ["classes"] = [new("name", "Class name", Required: true)],
        ["subjects"] = [new("name", "Subject name", Required: true), new("code", "Code")],
        ["attendance"] = [new("student_id", "Student ID", "number", true, Min: 1), new("attendance_date", "Date", "date", true), new("status", "Status", Options: ["present", "absent", "late", "excused"], Required: true), new("notes", "Notes")],
        ["timetable"] = [new("class_id", "Class ID", "number", true, Min: 1), new("subject_id", "Subject ID", "number", true, Min: 1), new("teacher_id", "Teacher ID", "number", Min: 1), new("weekday", "Weekday 1-7", "number", true, Min: 1), new("starts_at", "Starts at", "time", true), new("ends_at", "Ends at", "time", true), new("room", "Room")],
        ["fees"] = [new("student_id", "Student ID", "number", true, Min: 1), new("invoice_no", "Invoice number", Required: true), new("due_date", "Due date", "date", true), new("amount", "Amount", "number", true, Min: 0.01), new("status", "Status", Options: ["unpaid", "partial", "paid", "void"])],
        ["fee-structures"] = [new("name", "Structure name", Required: true), new("amount", "Amount", "number", true, Min: 0.01), new("frequency", "Frequency", Options: ["monthly", "term", "annual", "one_time"]), new("active", "Active", Options: ["true", "false"])],
        ["payments"] = [new("invoice_id", "Invoice ID", "number", true, Min: 1), new("amount", "Payment amount", "number", true, Min: 0.01), new("paid_on", "Paid on", "date", true), new("method", "Payment method", Options: ["cash", "bank", "card", "online"]), new("reference_no", "Reference number")],
        ["expenses"] = [new("category", "Category", Required: true), new("description", "Description", "multiline", true), new("amount", "Amount", "number", true, Min: 0.01), new("spent_on", "Date", "date", true), new("status", "Status", Options: ["draft", "approved", "paid", "void"])],
        ["exams"] = [new("name", "Exam name", Required: true), new("starts_on", "Starts on", "date", true), new("ends_on", "Ends on", "date", true), new("status", "Status", Options: ["draft", "published", "closed"])],
        ["exam-subjects"] = [new("exam_id", "Exam ID", "number", true, Min: 1), new("subject_id", "Subject ID", "number", true, Min: 1), new("max_marks", "Maximum marks", "number", true, Min: 1), new("passing_marks", "Passing marks", "number", true, Min: 0)],
        ["marks"] = [new("exam_subject_id", "Exam subject ID", "number", true, Min: 1), new("student_id", "Student ID", "number", true, Min: 1), new("marks", "Marks", "number", true, Min: 0), new("remarks", "Remarks")],
        ["results"] = [new("exam_id", "Exam ID", "number", true, Min: 1), new("student_id", "Student ID", "number", true, Min: 1), new("status", "Status", Options: ["draft", "published"])],
        ["payroll-runs"] = [new("period_start", "Period start", "date", true), new("period_end", "Period end", "date", true), new("status", "Status", Options: ["draft", "approved", "paid"])],
        ["payroll-items"] = [new("payroll_run_id", "Payroll run ID", "number", true, Min: 1), new("employee_id", "Employee ID", "number", true, Min: 1), new("gross_amount", "Gross amount", "number", true, Min: 0), new("deductions", "Deductions", "number", true, Min: 0), new("net_amount", "Net amount", "number", true, Min: 0)],
        ["library-books"] = [new("title", "Title", Required: true), new("author", "Author"), new("isbn", "ISBN"), new("total_copies", "Total copies", "number", Min: 0), new("available_copies", "Available copies", "number", Min: 0)],
        ["library-loans"] = [new("book_id", "Book ID", "number", true, Min: 1), new("student_id", "Student ID", "number", true, Min: 1), new("issued_on", "Issued on", "date", true), new("due_on", "Due on", "date", true), new("returned_on", "Returned on", "date"), new("status", "Status", Options: ["issued", "returned", "lost"])],
        ["transport-routes"] = [new("name", "Route name", Required: true), new("vehicle_no", "Vehicle number"), new("driver_employee_id", "Driver employee ID", "number", Min: 1)],
        ["transport-assignments"] = [new("route_id", "Route ID", "number", true, Min: 1), new("student_id", "Student ID", "number", true, Min: 1), new("pickup_point", "Pickup point", Required: true)],
        ["notices"] = [new("title", "Title", Required: true), new("body", "Notice body", "multiline", true), new("published_at", "Publish time", "datetime-local"), new("status", "Status", Options: ["draft", "published", "archived"])],
        ["reports"] = [new("report_type", "Report type", Options: ["attendance", "fees", "expenses", "results", "payroll", "enrollment"], Required: true), new("filters", "Filters JSON", "multiline")]
    };

    public MainWindow()
    {
        InitializeComponent();
        var apiBaseUrl = Environment.GetEnvironmentVariable("SCHOOL_SAAS_API_BASE_URL") ?? "http://localhost:8080";
        var dataDirectory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "SchoolSaaS");
        Directory.CreateDirectory(dataDirectory);
        _queue = new SyncQueue(Path.Combine(dataDirectory, "offline-sync.db"));
        _http = new HttpClient { BaseAddress = new Uri(apiBaseUrl.TrimEnd('/')) };
        _sync = new SyncService(apiBaseUrl, _queue);
        BuildModuleForm(_selectedModule);
    }

    private async void Login_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            StatusText.Text = "Signing in...";
            var response = await _http.PostAsJsonAsync("/api/v1/auth/login", new { email = EmailBox.Text, password = PasswordBox.Password });
            var json = await response.Content.ReadFromJsonAsync<JsonElement>();
            if (!response.IsSuccessStatusCode || !json.GetProperty("success").GetBoolean()) { StatusText.Text = json.TryGetProperty("message", out var message) ? message.GetString() : "Login failed"; return; }
            var data = json.GetProperty("data"); _token = data.GetProperty("token").GetString(); _role = data.GetProperty("user").GetProperty("role").GetString();
            _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", _token);
            var summary = await _sync.PushPendingAsync(_token!);
            StatusText.Text = summary.Total == 0 ? $"Signed in as {_role}. No offline work pending." : $"Signed in as {_role}. Sync complete: {summary.Applied} applied, {summary.Failed} pending/failed.";
            await RefreshModuleAsync(_selectedModule);
        }
        catch (Exception ex) { StatusText.Text = $"Connection error: {ex.Message}"; }
    }

    private async void RefreshModule_Click(object sender, RoutedEventArgs e) => await RefreshModuleAsync(_selectedModule);

    private async Task RefreshModuleAsync(string module)
    {
        if (_token is null) { StatusText.Text = "Login is required before loading records."; return; }
        if (!ModuleEndpoints.Contains(module)) { ModuleTitle.Text = "School Dashboard"; RecordsGrid.ItemsSource = null; return; }
        try
        {
            BuildModuleForm(module); ModuleTitle.Text = char.ToUpper(module[0]) + module[1..].Replace('-', ' '); StatusText.Text = $"Loading {ModuleTitle.Text.ToLowerInvariant()}...";
            var json = await _http.GetFromJsonAsync<JsonElement>($"/api/v1/{module}");
            if (!json.GetProperty("success").GetBoolean()) throw new InvalidOperationException(json.GetProperty("message").GetString());
            var rows = JsonSerializer.Deserialize<List<Dictionary<string, JsonElement>>>(json.GetProperty("data").GetRawText()) ?? [];
            RecordsGrid.ItemsSource = rows.Select(row => row.ToDictionary(pair => pair.Key, pair => DisplayValue(pair.Value))).ToList(); StatusText.Text = $"{rows.Count} {ModuleTitle.Text.ToLowerInvariant()} loaded.";
        }
        catch (Exception ex) { StatusText.Text = $"Could not load {ModuleTitle.Text.ToLowerInvariant()}: {ex.Message}. Offline queue remains safe."; }
    }

    private void BuildModuleForm(string module)
    {
        FieldPanel.Children.Clear(); _fields.Clear();
        if (!ModuleFields.TryGetValue(module, out var specs)) return;
        foreach (var spec in specs)
        {
            var group = new StackPanel { Width = spec.Type == "multiline" ? 220 : 150, Margin = new Thickness(0, 0, 10, 0) };
            group.Children.Add(new TextBlock { Text = spec.Label + (spec.Required ? " *" : ""), FontSize = 11, Foreground = System.Windows.Media.Brushes.SlateGray });
            Control input;
            if (spec.Options is not null)
            {
                var combo = new ComboBox { Width = 145, Height = 27, ItemsSource = spec.Options, Tag = spec.Key };
                input = combo;
            }
            else
            {
                var box = new TextBox { Width = 145, Height = spec.Type == "multiline" ? 52 : 27, AcceptsReturn = spec.Type == "multiline", TextWrapping = spec.Type == "multiline" ? TextWrapping.Wrap : TextWrapping.NoWrap, Tag = spec.Key };
                if (spec.Type == "date") box.ToolTip = "YYYY-MM-DD";
                if (spec.Type == "datetime-local") box.ToolTip = "YYYY-MM-DD HH:MM:SS";
                input = box;
            }
            group.Children.Add(input); FieldPanel.Children.Add(group); _fields[spec.Key] = input;
        }
    }

    private static object? DisplayValue(JsonElement value) => value.ValueKind switch { JsonValueKind.Null => null, JsonValueKind.String => value.GetString(), JsonValueKind.Number => value.ToString(), JsonValueKind.True or JsonValueKind.False => value.GetBoolean(), _ => value.ToString() };

    private Dictionary<string, object?> ReadForm()
    {
        var payload = new Dictionary<string, object?>();
        foreach (var spec in ModuleFields.GetValueOrDefault(_selectedModule, []))
        {
            var text = _fields[spec.Key] switch { TextBox box => box.Text.Trim(), ComboBox combo => combo.SelectedItem?.ToString() ?? "", _ => "" };
            if (spec.Required && string.IsNullOrWhiteSpace(text)) throw new InvalidOperationException($"{spec.Label} is required.");
            if (spec.Type == "number" && !string.IsNullOrWhiteSpace(text)) { if (!decimal.TryParse(text, out var number) || (spec.Min.HasValue && number < (decimal)spec.Min.Value)) throw new InvalidOperationException($"{spec.Label} is invalid."); payload[spec.Key] = number; }
            else if (spec.Key == "filters" && !string.IsNullOrWhiteSpace(text)) { try { payload[spec.Key] = JsonSerializer.Deserialize<JsonElement>(text); } catch { throw new InvalidOperationException("Filters must be valid JSON."); } }
            else payload[spec.Key] = text;
        }
        if (_selectedModule == "exams" && payload.TryGetValue("starts_on", out var start) && payload.TryGetValue("ends_on", out var end) && string.Compare(start?.ToString(), end?.ToString(), StringComparison.Ordinal) > 0) throw new InvalidOperationException("Exam end date must not precede start date.");
        if (_selectedModule == "timetable" && payload.TryGetValue("starts_at", out var starts) && payload.TryGetValue("ends_at", out var ends) && string.Compare(starts?.ToString(), ends?.ToString(), StringComparison.Ordinal) >= 0) throw new InvalidOperationException("Timetable end time must be after start time.");
        if (_selectedModule == "payments" && payload.TryGetValue("amount", out var paymentAmount) && (!decimal.TryParse(paymentAmount?.ToString(), out var parsedPayment) || parsedPayment <= 0)) throw new InvalidOperationException("Payment amount must be greater than zero.");
        if (_selectedModule == "fee-structures" && payload.TryGetValue("amount", out var structureAmount) && (!decimal.TryParse(structureAmount?.ToString(), out var parsedStructure) || parsedStructure <= 0)) throw new InvalidOperationException("Fee structure amount must be greater than zero.");
        if (_selectedModule == "payroll-items" && payload.TryGetValue("gross_amount", out var gross) && payload.TryGetValue("deductions", out var deductions) && decimal.TryParse(gross?.ToString(), out var parsedGross) && decimal.TryParse(deductions?.ToString(), out var parsedDeductions) && parsedDeductions > parsedGross) throw new InvalidOperationException("Deductions cannot exceed gross amount.");
        if (_selectedModule == "library-loans" && payload.TryGetValue("issued_on", out var issued) && payload.TryGetValue("due_on", out var due) && string.Compare(issued?.ToString(), due?.ToString(), StringComparison.Ordinal) > 0) throw new InvalidOperationException("Loan due date must not precede issue date.");
        if (_selectedModule == "transport-assignments" && payload.TryGetValue("pickup_point", out var pickup) && string.IsNullOrWhiteSpace(pickup?.ToString())) throw new InvalidOperationException("Pickup point is required.");
        return payload;
    }

    private void FillForm(Dictionary<string, object?> row)
    {
        if (row.TryGetValue("id", out var id)) RecordIdBox.Text = id?.ToString();
        foreach (var spec in ModuleFields.GetValueOrDefault(_selectedModule, [])) if (_fields.TryGetValue(spec.Key, out var control) && row.TryGetValue(spec.Key, out var value)) { var text = value?.ToString() ?? ""; if (control is TextBox box) box.Text = text; else if (control is ComboBox combo) combo.SelectedItem = text; }
    }

    private async void Create_Click(object sender, RoutedEventArgs e) { await SubmitMutationAsync(HttpMethod.Post, $"/api/v1/{_selectedModule}", "create"); }
    private async void Update_Click(object sender, RoutedEventArgs e) { if (!long.TryParse(RecordIdBox.Text, out var id) || id <= 0) { StatusText.Text = "Select a valid record before updating."; return; } await SubmitMutationAsync(HttpMethod.Patch, $"/api/v1/{_selectedModule}/{id}", "update"); }
    private async void Delete_Click(object sender, RoutedEventArgs e) { if (!long.TryParse(RecordIdBox.Text, out var id) || id <= 0) { StatusText.Text = "Select a valid record before deleting."; return; } await SubmitMutationAsync(HttpMethod.Delete, $"/api/v1/{_selectedModule}/{id}", "delete", new Dictionary<string, object?>()); }

    private async Task SubmitMutationAsync(HttpMethod method, string endpoint, string operationType, Dictionary<string, object?>? overridePayload = null)
    {
        if (_token is null) { StatusText.Text = "Login is required before saving records."; return; }
        try
        {
            var payload = overridePayload ?? ReadForm(); var jsonPayload = JsonSerializer.Serialize(payload);
            using var request = new HttpRequestMessage(method, endpoint) { Content = new StringContent(jsonPayload, Encoding.UTF8, "application/json") };
            using var response = await _http.SendAsync(request); var json = await response.Content.ReadFromJsonAsync<JsonElement>();
            if (!response.IsSuccessStatusCode || !json.GetProperty("success").GetBoolean()) { StatusText.Text = json.TryGetProperty("message", out var error) ? error.GetString() : "API rejected the operation"; return; }
            ClearForm(); StatusText.Text = $"{operationType} applied to {_selectedModule}."; await RefreshModuleAsync(_selectedModule);
        }
        catch (InvalidOperationException ex) { StatusText.Text = ex.Message; }
        catch (HttpRequestException ex)
        {
            var payload = overridePayload ?? ReadForm(); _queue.Enqueue(method.Method, endpoint, JsonSerializer.Serialize(payload), entity: _selectedModule, operationType: operationType);
            StatusText.Text = $"Offline: {operationType} queued safely. It will sync when the connection returns. {ex.Message}";
        }
        catch (Exception ex) { StatusText.Text = $"Could not save {_selectedModule}: {ex.Message}"; }
    }

    private void ClearForm() { RecordIdBox.Clear(); foreach (var control in _fields.Values) { if (control is TextBox box) box.Clear(); else if (control is ComboBox combo) combo.SelectedIndex = -1; } }
    private async void Sync_Click(object sender, RoutedEventArgs e) { if (_token is null) { StatusText.Text = "Login is required before synchronizing."; return; } var summary = await _sync.PushPendingAsync(_token); StatusText.Text = $"Sync complete: {summary.Applied} applied, {summary.Failed} pending/failed."; await RefreshModuleAsync(_selectedModule); }
    private async void Navigate_Click(object sender, RoutedEventArgs e) { var target = (sender as FrameworkElement)?.Tag?.ToString() ?? "dashboard"; if (target == "sync") { Sync_Click(sender, e); return; } if (target == "dashboard") { _selectedModule = "students"; ModuleTitle.Text = "School Dashboard"; StatusText.Text = "Choose a module to load tenant records."; RecordsGrid.ItemsSource = null; BuildModuleForm("students"); return; } _selectedModule = target; await RefreshModuleAsync(target); }
    private void RecordsGrid_SelectionChanged(object sender, SelectionChangedEventArgs e) { if (RecordsGrid.SelectedItem is Dictionary<string, object?> row) FillForm(row); }
}
