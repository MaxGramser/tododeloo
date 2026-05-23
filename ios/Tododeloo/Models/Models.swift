import Foundation

// MARK: - Domain models

struct User: Codable, Hashable, Identifiable {
    let id: Int
    let name: String
    let email: String
}

struct Tag: Codable, Hashable, Identifiable {
    let id: Int
    let name: String
    let color: String?
}

struct SubTodo: Codable, Hashable, Identifiable {
    let id: Int
    var title: String
    var completedAt: Date?
    var position: Int

    var isCompleted: Bool { completedAt != nil }
}

struct ListMembership: Codable, Hashable, Identifiable {
    let id: Int
    let type: String
    let label: String
}

enum Priority: String, CaseIterable, Identifiable {
    case low
    case normal
    case high

    var id: String { rawValue }

    var label: String {
        switch self {
        case .low: return "Laag"
        case .normal: return "Normaal"
        case .high: return "Hoog"
        }
    }
}

struct Todo: Codable, Hashable, Identifiable {
    let id: Int
    var title: String
    var description: String?
    var priority: String
    var completedAt: Date?
    var createdAt: Date?
    var tags: [Tag]?
    var position: Int?
    var listMemberships: [ListMembership]?
    var subTodos: [SubTodo]?
    var recurrenceId: Int?
    var recurrence: Recurrence?

    var isCompleted: Bool { completedAt != nil }
    var priorityValue: Priority { Priority(rawValue: priority) ?? .normal }
    var openSubTodoCount: Int { (subTodos ?? []).filter { !$0.isCompleted }.count }
    var totalSubTodoCount: Int { (subTodos ?? []).count }
    var isRecurring: Bool { recurrenceId != nil }

    /// Custom/daily list memberships, excluding the implicit master list.
    var otherMemberships: [ListMembership] { listMemberships ?? [] }

    /// A local placeholder shown instantly while the server creates the real
    /// todo. Its negative id never collides with a server id.
    static func draft(title: String) -> Todo {
        Todo(
            id: Int.random(in: Int.min ..< 0),
            title: title,
            description: nil,
            priority: Priority.normal.rawValue,
            completedAt: nil,
            createdAt: Date(),
            tags: [],
            position: nil,
            listMemberships: nil,
            subTodos: [],
            recurrenceId: nil,
            recurrence: nil
        )
    }

    var isDraft: Bool { id < 0 }
}

struct Recurrence: Codable, Hashable, Identifiable {
    let id: Int
    let rrule: String
    let active: Bool
    /// Key of the matching preset, or nil for a custom rule.
    let preset: String?
    /// Readable Dutch description, e.g. "Elke werkdag". Optional so the app still
    /// decodes against a backend that predates this field (e.g. production).
    let summary: String?
}

/// How a list orders its todos. Mirrors the server's sort_mode enum.
enum SortMode: String, CaseIterable, Identifiable {
    case manual
    case createdAt = "created_at"
    case alphabetical
    case priority

    var id: String { rawValue }

    var label: String {
        switch self {
        case .manual: return "Handmatig"
        case .createdAt: return "Nieuwste eerst"
        case .alphabetical: return "Alfabetisch"
        case .priority: return "Prioriteit"
        }
    }

    var icon: String {
        switch self {
        case .manual: return "hand.draw"
        case .createdAt: return "clock"
        case .alphabetical: return "textformat"
        case .priority: return "flag"
        }
    }
}

/// A repeat option shown in the press-and-hold menu. Labels are derived from the
/// day the todo sits on (so "weekly" reads as "Elke dinsdag"); the server turns
/// the chosen key + anchor into the actual RRULE.
struct RecurrencePresetOption: Identifiable, Hashable {
    let key: String
    let label: String

    var id: String { key }

    static func presets(anchorISO: String) -> [RecurrencePresetOption] {
        let anchor = dayFormatter.date(from: anchorISO) ?? Date()
        let calendar = Calendar(identifier: .gregorian)
        let weekdays = ["zondag", "maandag", "dinsdag", "woensdag", "donderdag", "vrijdag", "zaterdag"]
        let weekday = weekdays[(calendar.component(.weekday, from: anchor) - 1) % 7]
        let nth = Int(ceil(Double(calendar.component(.day, from: anchor)) / 7.0))
        let ordinals = ["", "1e", "2e", "3e", "4e"]
        let nthLabel = nth >= 5 ? "laatste" : ordinals[nth]

        return [
            .init(key: "daily", label: "Elke dag"),
            .init(key: "weekdays", label: "Elke werkdag"),
            .init(key: "weekly", label: "Elke \(weekday)"),
            .init(key: "monthly_nth_weekday", label: "Maandelijks · \(nthLabel) \(weekday)"),
            .init(key: "half_yearly", label: "Elk half jaar"),
            .init(key: "yearly", label: "Elk jaar"),
        ]
    }

    static let dayFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter
    }()
}

struct TodoList: Codable, Hashable, Identifiable {
    let id: Int
    let type: String
    var name: String?
    var date: String?
    var sortMode: String
    var todos: [Todo]?

    var isMaster: Bool { type == "master" }
    var isDaily: Bool { type == "daily" }
    var isCustom: Bool { type == "custom" }

    var displayName: String {
        switch type {
        case "master": return "Alles"
        case "daily": return date ?? "Dag"
        default: return name ?? "Lijst"
        }
    }
}

struct ListSummary: Codable, Hashable, Identifiable {
    let id: Int
    let type: String
    var name: String?
    var date: String?
    var sortMode: String
    var openCount: Int
    var totalCount: Int

    var isMaster: Bool { type == "master" }

    var displayName: String {
        switch type {
        case "master": return "Alles"
        default: return name ?? "Lijst"
        }
    }
}

// MARK: - Response envelopes

struct LoginResponse: Codable { let token: String; let user: User }
struct UserResponse: Codable { let user: User }
struct TodoResponse: Codable { let todo: Todo }
struct ListResponse: Codable { let list: TodoList }
struct ListsResponse: Codable { let lists: [ListSummary] }
struct TagResponse: Codable { let tag: Tag }
struct TagsResponse: Codable { let tags: [Tag] }
struct QuickAddResponse: Codable {
    let todo: Todo
    let targetDate: String?
    /// Human-readable confirmation of what the parser scheduled. Optional so the
    /// app still decodes against a backend that predates this field.
    let feedback: QuickAddFeedback?
}

/// Backend-built confirmation copy, shared verbatim by web, iOS/Mac and Siri.
struct QuickAddFeedback: Codable, Hashable {
    /// The quoted todo title, e.g. `"Bel de dokter"`.
    let message: String
    /// Where it landed, e.g. "ingepland voor volgende week dinsdag".
    let description: String?
}

struct TodayResponse: Codable {
    let date: String
    let isToday: Bool
    let list: TodoList?
    let needsRitual: Bool
    let previousWorkday: String
    let carryOverCandidates: [Todo]
    let earlierCandidates: [Todo]
    let masterOpenTodos: [Todo]
    let preScheduled: [Todo]
}
