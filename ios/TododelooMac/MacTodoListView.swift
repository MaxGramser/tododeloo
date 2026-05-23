import SwiftUI

/// The middle column: a quick-add field, the todo list, and a sort/filter
/// toolbar. Selection is drawn ourselves (a soft accent fill, not the system
/// blue) and drives the inspector. Backspace deletes the selected todo, the
/// arrow keys move the selection, and double-clicking a title renames it.
/// For the Today board it also gets day navigation.
struct MacTodoListView: View {
    @Bindable var model: BoardModel
    let lists: ListsModel
    @Binding var selectedTodoID: Int?
    // Bumped by the menu commands to focus quick-add, rename, or move the
    // selected todo — things the list owns the @FocusState / sheet state for.
    var focusQuickAddToken = 0
    var renameSelectedToken = 0
    var moveSelectedToken = 0
    @FocusState private var quickAddFocused: Bool

    @State private var renamingID: Int?
    @State private var renameText = ""
    @State private var customRecurrenceTodo: Todo?
    @State private var moveTarget: Todo?

    var body: some View {
        VStack(spacing: 0) {
            quickAdd
            list
        }
        .navigationTitle(model.title)
        .navigationSubtitle(subtitle)
        .toolbar { toolbar }
        .onChange(of: focusQuickAddToken) { _, _ in quickAddFocused = true }
        .onChange(of: renameSelectedToken) { _, _ in
            if let todo = model.visibleTodos.first(where: { $0.id == selectedTodoID }) {
                startRename(todo)
            }
        }
        .onChange(of: moveSelectedToken) { _, _ in
            if let todo = model.visibleTodos.first(where: { $0.id == selectedTodoID }) {
                moveTarget = todo
            }
        }
        .sheet(item: $customRecurrenceTodo) { todo in
            MacRecurrenceSheet(anchorISO: model.recurrenceAnchorISO) { rrule in
                Task { await model.setCustomRecurrence(todo, rrule: rrule) }
            }
        }
        .sheet(item: $moveTarget) { todo in
            MacMoveDateSheet(initialISO: todo.scheduledFor) { date in
                Task { await model.move(todo, toDate: date) }
            }
        }
    }

    private var subtitle: String {
        "\(model.openCount) open"
    }

    /// A tidy, theme-coloured input at the top of the column (a centered toolbar
    /// field would sit crooked over the split-view's midpoint).
    private var quickAdd: some View {
        VStack(spacing: 0) {
            HStack(spacing: 9) {
                Image(systemName: "plus")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(Theme.accent)
                TextField("Snel toevoegen…", text: $model.quickAddText)
                    .textFieldStyle(.plain)
                    .font(.system(size: 13, weight: .medium))
                    .focused($quickAddFocused)
                    .onSubmit {
                        Task {
                            await model.submitQuickAdd()
                            quickAddFocused = true
                        }
                    }
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 9)

            // The preview is the bottom half of the same card — it grows out of
            // the field when you type something parseable.
            ParsePreviewStrip(text: model.quickAddText, style: .attached)
        }
        .background(Theme.surface)
        .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 10, style: .continuous)
                .strokeBorder(Theme.hairline, lineWidth: 1)
        )
        .padding(.horizontal, 14)
        .padding(.top, 12)
        .padding(.bottom, 8)
        .background(Theme.background)
    }

    private var list: some View {
        List {
            ForEach(model.visibleTodos) { todo in
                MacTodoRow(
                    todo: todo,
                    isRenaming: renamingID == todo.id,
                    showsSchedule: !model.isDayBoard,
                    renameText: $renameText,
                    onToggle: { Task { await model.toggle(todo) } },
                    onSelect: { selectedTodoID = todo.id },
                    onStartRename: { startRename(todo) },
                    onCommitRename: { commitRename(todo) },
                    onCancelRename: cancelRename
                )
                .listRowSeparator(.hidden)
                .listRowInsets(EdgeInsets(top: 0, leading: 0, bottom: 0, trailing: 0))
                .listRowBackground(selectedTodoID == todo.id ? Theme.accent.opacity(0.13) : Color.clear)
                .contextMenu {
                    MacTodoMenu(
                        model: model,
                        lists: lists,
                        todo: todo,
                        onRename: { startRename(todo) },
                        onMove: { moveTarget = todo },
                        onCustomRecurrence: { customRecurrenceTodo = todo }
                    )
                }
            }
        }
        .listStyle(.plain)
        .scrollContentBackground(.hidden)
        .background(Theme.background)
        .animation(.snappy(duration: 0.25), value: model.visibleTodos)
        .animation(.easeInOut(duration: 0.12), value: selectedTodoID)
        .onDeleteCommand(perform: deleteSelected)
        .onMoveCommand(perform: moveSelection)
        .onChange(of: selectedTodoID) { _, newID in
            // Moving to another row commits the rename you were in (macOS-style).
            guard let editing = renamingID, editing != newID else { return }
            if let todo = model.visibleTodos.first(where: { $0.id == editing }) {
                commitRename(todo)
            } else {
                renamingID = nil
            }
        }
        .overlay {
            if model.visibleTodos.isEmpty && model.hasLoaded {
                ContentUnavailableView(
                    model.hasActiveFilter ? "Geen resultaten" : "Niets hier",
                    systemImage: model.hasActiveFilter ? "line.3.horizontal.decrease.circle" : "checklist",
                    description: Text(model.hasActiveFilter ? "Geen taken voldoen aan dit filter." : "Voeg hierboven iets toe.")
                )
            }
        }
    }

    // MARK: - Selection & rename

    private func deleteSelected() {
        guard let id = selectedTodoID,
              let todo = model.visibleTodos.first(where: { $0.id == id }) else { return }
        let ids = model.visibleTodos.map(\.id)
        var next: Int?
        if let i = ids.firstIndex(of: id) {
            if i + 1 < ids.count { next = ids[i + 1] } else if i > 0 { next = ids[i - 1] }
        }
        selectedTodoID = next
        Task { await model.delete(todo) }
    }

    private func moveSelection(_ direction: MoveCommandDirection) {
        let ids = model.visibleTodos.map(\.id)
        guard !ids.isEmpty else { return }
        guard let current = selectedTodoID, let index = ids.firstIndex(of: current) else {
            selectedTodoID = direction == .up ? ids.last : ids.first
            return
        }
        switch direction {
        case .up: selectedTodoID = ids[max(0, index - 1)]
        case .down: selectedTodoID = ids[min(ids.count - 1, index + 1)]
        default: break
        }
    }

    private func startRename(_ todo: Todo) {
        // Committing any rename already in progress before starting a new one.
        if let editing = renamingID, editing != todo.id,
           let prev = model.visibleTodos.first(where: { $0.id == editing }) {
            commitRename(prev)
        }
        renameText = todo.title
        renamingID = todo.id
        selectedTodoID = todo.id
    }

    private func commitRename(_ todo: Todo) {
        guard renamingID == todo.id else { return }
        let trimmed = renameText.trimmingCharacters(in: .whitespacesAndNewlines)
        renamingID = nil
        guard !trimmed.isEmpty, trimmed != todo.title else { return }
        Task { await model.rename(todo, title: trimmed) }
    }

    private func cancelRename() {
        renamingID = nil
    }

    // MARK: - Toolbar

    @ToolbarContentBuilder
    private var toolbar: some ToolbarContent {
        if model.isDayBoard {
            ToolbarItemGroup(placement: .navigation) {
                Button { Task { await model.shiftDay(by: -1) } } label: {
                    Image(systemName: "chevron.left")
                }
                if !model.isViewingToday {
                    Button("Vandaag") { Task { await model.goToToday() } }
                }
                Button { Task { await model.shiftDay(by: 1) } } label: {
                    Image(systemName: "chevron.right")
                }
            }
        }

        ToolbarItem {
            Menu {
                Picker("Sorteren", selection: sortBinding) {
                    ForEach(SortMode.allCases) { mode in
                        Label(mode.label, systemImage: mode.icon).tag(mode)
                    }
                }
                Divider()
                filterMenu
                if model.context == .today && model.isViewingToday {
                    Divider()
                    Button("Ritueel opnieuw") { Task { await model.resetRitual() } }
                }
            } label: {
                Image(systemName: model.hasActiveFilter
                    ? "line.3.horizontal.decrease.circle.fill"
                    : "line.3.horizontal.decrease.circle")
            }
        }
    }

    private var sortBinding: Binding<SortMode> {
        Binding(get: { model.sortMode }, set: { mode in Task { await model.setSortMode(mode) } })
    }

    @ViewBuilder
    private var filterMenu: some View {
        Menu("Prioriteit") {
            checkButton("Alle prioriteiten", model.priorityFilter == nil) { model.priorityFilter = nil }
            ForEach([Priority.high, .normal, .low]) { p in
                checkButton(p.label, model.priorityFilter == p) {
                    model.priorityFilter = model.priorityFilter == p ? nil : p
                }
            }
        }
        if !model.availableTags.isEmpty {
            Menu("Tag") {
                checkButton("Alle tags", model.tagFilter == nil) { model.tagFilter = nil }
                ForEach(model.availableTags) { tag in
                    checkButton(tag.name, model.tagFilter == tag.id) {
                        model.tagFilter = model.tagFilter == tag.id ? nil : tag.id
                    }
                }
            }
        }
        if model.hasActiveFilter {
            Divider()
            Button("Filter wissen", role: .destructive) { model.clearFilters() }
        }
    }

    @ViewBuilder
    private func checkButton(_ title: String, _ on: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            if on { Label(title, systemImage: "checkmark") } else { Text(title) }
        }
    }
}

/// One todo row. The leading control is a completion circle, or — when the todo
/// has sub-tasks — a progress ring you tap to open it (you finish the subs, not
/// the parent). Double-clicking the title renames inline.
struct MacTodoRow: View {
    let todo: Todo
    let isRenaming: Bool
    /// Show a calendar chip with the day the todo is scheduled on. Off on the
    /// day boards, where the date is already the context.
    var showsSchedule = false
    @Binding var renameText: String
    let onToggle: () -> Void
    let onSelect: () -> Void
    let onStartRename: () -> Void
    let onCommitRename: () -> Void
    let onCancelRename: () -> Void
    @FocusState private var renameFocused: Bool

    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            leadingControl

            VStack(alignment: .leading, spacing: 3) {
                if isRenaming {
                    TextField("", text: $renameText)
                        .textFieldStyle(.plain)
                        .font(.system(size: 14))
                        .foregroundStyle(Theme.ink)
                        .focused($renameFocused)
                        .onSubmit(onCommitRename)
                        .onExitCommand(perform: onCancelRename)
                        .onChange(of: renameFocused) { _, isFocused in
                            if !isFocused { onCommitRename() }
                        }
                        .onAppear { renameFocused = true }
                } else {
                    Text(todo.title)
                        .font(.system(size: 14))
                        .foregroundStyle(todo.isCompleted ? Theme.faint : Theme.ink)
                        .strikethrough(todo.isCompleted, color: Theme.faint)
                        .lineLimit(2)
                        .onTapGesture(count: 2, perform: onStartRename)
                }
                metadata
            }
            Spacer(minLength: 0)
        }
        .padding(.vertical, 7)
        .padding(.horizontal, 16)
        .contentShape(Rectangle())
        .onTapGesture(perform: onSelect)
    }

    @ViewBuilder
    private var leadingControl: some View {
        if todo.hasSubTodos {
            Button(action: onSelect) {
                SubProgressRing(done: todo.doneSubTodoCount, total: todo.totalSubTodoCount, size: 18)
            }
            .buttonStyle(.plain)
            .help("Open om de subtaken af te ronden")
        } else {
            Button(action: onToggle) {
                Image(systemName: todo.isCompleted ? "largecircle.fill.circle" : "circle")
                    .font(.system(size: 16))
                    .foregroundStyle(toggleColor)
                    .contentTransition(.symbolEffect(.replace))
            }
            .buttonStyle(.plain)
        }
    }

    private var toggleColor: Color {
        if todo.isCompleted { return Theme.accent }
        return todo.priorityValue == .high ? Theme.accent : Theme.faint
    }

    @ViewBuilder
    private var metadata: some View {
        let tags = todo.tags ?? []
        // The day-membership is surfaced as the schedule chip instead.
        let memberships = todo.otherMemberships.filter { $0.type != "daily" }
        let showsScheduleChip = showsSchedule && todo.scheduledFor != nil
        let hasMeta = todo.priorityValue == .high
            || todo.isRecurring
            || !tags.isEmpty
            || todo.totalSubTodoCount > 0
            || !memberships.isEmpty
            || showsScheduleChip

        if hasMeta {
            HStack(spacing: 6) {
                if let scheduled = todo.scheduledFor, showsSchedule {
                    HStack(spacing: 3) {
                        Image(systemName: "calendar").font(.system(size: 9, weight: .semibold))
                        Text(DateText.relative(scheduled).uppercased())
                            .font(.mono(10, weight: .semibold)).tracking(1)
                    }
                    .foregroundStyle(Theme.accent)
                    .lineLimit(1)
                }
                if todo.priorityValue == .high {
                    MonoLabel("Hoog", color: Theme.accent)
                }
                if let summary = todo.recurrence?.summary {
                    HStack(spacing: 3) {
                        Image(systemName: "repeat").font(.system(size: 9, weight: .semibold))
                        Text(summary.uppercased()).font(.mono(10, weight: .semibold)).tracking(1)
                    }
                    .foregroundStyle(Theme.faint)
                    .lineLimit(1)
                } else if todo.isRecurring {
                    Image(systemName: "repeat").font(.system(size: 9, weight: .semibold)).foregroundStyle(Theme.faint)
                }
                ForEach(tags) { tag in TagChip(tag: tag) }
                if todo.totalSubTodoCount > 0 {
                    MonoLabel("\(todo.doneSubTodoCount)/\(todo.totalSubTodoCount)")
                }
                ForEach(memberships) { membership in
                    MonoLabel(membership.label, color: Theme.faint)
                }
            }
        }
    }
}

/// The right-click menu for a row. Reuses every BoardModel action.
struct MacTodoMenu: View {
    @Bindable var model: BoardModel
    let lists: ListsModel
    let todo: Todo
    let onRename: () -> Void
    let onMove: () -> Void
    let onCustomRecurrence: () -> Void

    var body: some View {
        // A todo with sub-tasks is completed by finishing its subs, not directly.
        if !todo.hasSubTodos {
            Button(todo.isCompleted ? "Markeer onaf" : "Markeer af") {
                Task { await model.toggle(todo) }
            }
        }
        Button("Hernoem", action: onRename)
        if model.context != .today {
            Button("Naar vandaag") { Task { await model.addToToday(todo) } }
        }

        Menu("Verplaats naar") {
            Button("Morgen") { Task { await model.move(todo, toDate: DateText.offset(1)) } }
            Button("Over een week") { Task { await model.move(todo, toDate: DateText.offset(7)) } }
            Divider()
            Button("Kies datum…", action: onMove)
        }

        Menu("Prioriteit") {
            ForEach(Priority.allCases) { priority in
                Button { Task { await model.setPriority(todo, priority) } } label: {
                    if todo.priorityValue == priority {
                        Label(priority.label, systemImage: "checkmark")
                    } else {
                        Text(priority.label)
                    }
                }
            }
        }

        Menu(recurrenceTitle) {
            ForEach(model.recurrencePresets) { preset in
                Button { Task { await model.setRecurrence(todo, preset: preset.key) } } label: {
                    if todo.recurrence?.preset == preset.key {
                        Label(preset.label, systemImage: "checkmark")
                    } else {
                        Text(preset.label)
                    }
                }
            }
            Divider()
            Button("Aangepast…", action: onCustomRecurrence)
            if todo.isRecurring {
                Divider()
                Button("Stop herhaling", role: .destructive) { Task { await model.stopRecurrence(todo) } }
            }
        }

        if !model.addableLists.isEmpty {
            Menu("Zet op lijst") {
                ForEach(model.addableLists) { list in
                    Button(list.displayName) { Task { await model.addToList(todo, listId: list.id) } }
                }
            }
        }

        Button("Dupliceer") { Task { await model.duplicate(todo) } }

        if model.canRemoveFromList {
            Button("Uit deze lijst halen") { Task { await model.removeFromList(todo) } }
        }

        Divider()
        Button("Verwijder", role: .destructive) { Task { await model.delete(todo) } }
    }

    private var recurrenceTitle: String {
        if let summary = todo.recurrence?.summary { return "Herhaal · \(summary)" }
        return todo.isRecurring ? "Herhaal · aan" : "Herhaal"
    }
}

/// A small sheet with a calendar to reschedule a todo to an exact date.
/// Pre-selects the day the todo currently sits on, if any.
struct MacMoveDateSheet: View {
    @Environment(\.dismiss) private var dismiss
    let initialISO: String?
    let onPick: (String) -> Void
    @State private var date: Date

    init(initialISO: String?, onPick: @escaping (String) -> Void) {
        self.initialISO = initialISO
        self.onPick = onPick
        _date = State(initialValue: initialISO.flatMap(DateText.parse) ?? Date())
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            Text("Verplaats naar").font(.headline)
            DatePicker("", selection: $date, displayedComponents: .date)
                .datePickerStyle(.graphical)
                .labelsHidden()
                .tint(Theme.accent)
                .frame(width: 280)
            HStack {
                Spacer()
                Button("Annuleer") { dismiss() }
                Button("Verplaats") {
                    onPick(DateText.ymd(date))
                    dismiss()
                }
                .buttonStyle(.borderedProminent)
                .tint(Theme.ink)
            }
        }
        .padding(22)
    }
}

/// Builds an arbitrary RFC 5545 RRULE on the Mac (every N days/weeks/months/
/// years, weekday picks, monthly-on-the-Nth-weekday). Anchored on the day the
/// todo sits on. Mirrors the iOS RecurrenceSheet and the web builder.
struct MacRecurrenceSheet: View {
    @Environment(\.dismiss) private var dismiss
    let anchorISO: String
    let onSave: (String) -> Void

    private let weekdayCodes = ["MO", "TU", "WE", "TH", "FR", "SA", "SU"]
    private let weekdayLabels = ["Ma", "Di", "Wo", "Do", "Vr", "Za", "Zo"]
    private let weekdayNames = ["zondag", "maandag", "dinsdag", "woensdag", "donderdag", "vrijdag", "zaterdag"]
    private let ordinals = ["", "1e", "2e", "3e", "4e"]
    private let freqs: [(code: String, label: String)] = [
        ("DAILY", "Dag"), ("WEEKLY", "Week"), ("MONTHLY", "Maand"), ("YEARLY", "Jaar"),
    ]

    @State private var freq = "WEEKLY"
    @State private var interval = 1
    @State private var days: Set<String> = []
    @State private var monthlyWeekday = true

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            Text("Herhaling").font(.headline)

            Picker("", selection: $freq) {
                ForEach(freqs, id: \.code) { Text($0.label).tag($0.code) }
            }
            .pickerStyle(.segmented)
            .labelsHidden()

            Stepper("Elke \(interval) \(unitLabel)", value: $interval, in: 1 ... 99)
                .font(.system(size: 13, weight: .medium))

            if freq == "WEEKLY" {
                HStack(spacing: 6) {
                    ForEach(Array(zip(weekdayCodes, weekdayLabels)), id: \.0) { code, label in
                        Button { toggle(code) } label: {
                            Text(label)
                                .font(.system(size: 12, weight: .semibold))
                                .frame(width: 32, height: 32)
                                .background(Circle().fill(days.contains(code) ? Theme.accent : Color.clear))
                                .foregroundStyle(days.contains(code) ? Theme.background : Theme.muted)
                                .overlay(Circle().stroke(days.contains(code) ? Color.clear : Theme.faint, lineWidth: 1))
                        }
                        .buttonStyle(.plain)
                    }
                }
            }

            if freq == "MONTHLY" {
                VStack(alignment: .leading, spacing: 8) {
                    radio("Op de \(nthLabel) \(weekdayName)", monthlyWeekday) { monthlyWeekday = true }
                    radio("Op dag \(dayOfMonth) van de maand", !monthlyWeekday) { monthlyWeekday = false }
                }
            }

            Text(rrule)
                .font(.system(size: 11, design: .monospaced))
                .foregroundStyle(Theme.faint)

            HStack {
                Spacer()
                Button("Annuleer") { dismiss() }
                Button("Bewaar") { onSave(rrule); dismiss() }
                    .buttonStyle(.borderedProminent)
                    .tint(Theme.ink)
            }
        }
        .padding(22)
        .frame(width: 360)
        .onAppear { days = [anchorCode] }
    }

    private var unitLabel: String {
        let plural = interval > 1
        switch freq {
        case "DAILY": return plural ? "dagen" : "dag"
        case "WEEKLY": return plural ? "weken" : "week"
        case "MONTHLY": return plural ? "maanden" : "maand"
        default: return plural ? "jaren" : "jaar"
        }
    }

    private func radio(_ title: String, _ on: Bool, _ action: @escaping () -> Void) -> some View {
        Button(action: action) {
            HStack(spacing: 10) {
                Circle().fill(on ? Theme.accent : Theme.faint.opacity(0.4)).frame(width: 8, height: 8)
                Text(title).font(.system(size: 13)).foregroundStyle(Theme.ink)
                Spacer()
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 9)
            .overlay(RoundedRectangle(cornerRadius: 8).stroke(on ? Theme.accent : Theme.faint, lineWidth: 1))
        }
        .buttonStyle(.plain)
    }

    private func toggle(_ code: String) {
        if days.contains(code) { days.remove(code) } else { days.insert(code) }
    }

    private var anchorDate: Date { RecurrencePresetOption.dayFormatter.date(from: anchorISO) ?? Date() }
    private var anchorCode: String {
        let weekday = Calendar(identifier: .gregorian).component(.weekday, from: anchorDate)
        return weekdayCodes[(weekday - 2 + 7) % 7]
    }

    private var dayOfMonth: Int { Calendar(identifier: .gregorian).component(.day, from: anchorDate) }
    private var nth: Int { let n = Int(ceil(Double(dayOfMonth) / 7.0)); return n >= 5 ? -1 : n }
    private var nthLabel: String { nth == -1 ? "laatste" : ordinals[nth] }
    private var weekdayName: String {
        weekdayNames[Calendar(identifier: .gregorian).component(.weekday, from: anchorDate) - 1]
    }

    private var rrule: String {
        var parts = ["FREQ=\(freq)"]
        if interval > 1 { parts.append("INTERVAL=\(interval)") }
        if freq == "WEEKLY" {
            let ordered = weekdayCodes.filter { days.contains($0) }
            parts.append("BYDAY=\((ordered.isEmpty ? [anchorCode] : ordered).joined(separator: ","))")
        }
        if freq == "MONTHLY" {
            if monthlyWeekday {
                parts.append("BYDAY=\(nth)\(anchorCode)")
            } else {
                parts.append("BYMONTHDAY=\(dayOfMonth)")
            }
        }
        return parts.joined(separator: ";")
    }
}
