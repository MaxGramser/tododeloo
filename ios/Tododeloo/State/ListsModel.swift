import Observation
import SwiftUI

/// Backs the "Lijsten" overview: the master list plus every custom list, with
/// open-todo counts. Handles creating, renaming and deleting custom lists.
@MainActor
@Observable
final class ListsModel {
    var lists: [ListSummary] = []
    var isLoading = false
    var hasLoaded = false
    var errorMessage: String?

    private let api = APIClient.shared
    var onUnauthorized: (() -> Void)?

    var master: ListSummary? { lists.first { $0.isMaster } }
    var customLists: [ListSummary] { lists.filter { !$0.isMaster } }

    func load() async {
        isLoading = true
        defer { isLoading = false; hasLoaded = true }
        do {
            lists = try await api.lists()
            errorMessage = nil
        } catch {
            handle(error)
        }
    }

    func createList(name: String) async {
        let trimmed = name.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        do {
            _ = try await api.createList(name: trimmed)
            await load()
        } catch {
            handle(error)
        }
    }

    func rename(_ list: ListSummary, name: String) async {
        let trimmed = name.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !trimmed.isEmpty else { return }
        do {
            _ = try await api.renameList(list.id, name: trimmed)
            await load()
        } catch {
            handle(error)
        }
    }

    func delete(_ list: ListSummary) async {
        guard !list.isMaster else { return }
        lists.removeAll { $0.id == list.id }
        do {
            try await api.deleteList(list.id)
        } catch {
            handle(error)
            await load()
        }
    }

    private func handle(_ error: Error) {
        if case APIError.unauthorized = error {
            onUnauthorized?()
            return
        }
        errorMessage = (error as? APIError)?.errorDescription ?? error.localizedDescription
    }
}
